/**
 * Issue Tracker Kanban Board Drag & Drop
 * Lightweight vanilla JS implementation for drag and drop functionality
 * 
 * @package issue_tracker
 */

(function() {
    'use strict';
    
    var IssueTrackerBoard = {
        draggedElement: null,
        draggedFrom: null,
        placeholder: null,
        
        init: function() {
            var board = document.getElementById('kanban-board');
            if (!board) return;
            
            this.projectId = board.dataset.projectId;
            this.canWrite = board.dataset.canWrite === '1';
            
            // Only enable drag and drop if user has write permission
            if (!this.canWrite) return;
            
            this.columns = board.querySelectorAll('.kanban-column-body');
            this.setupDragAndDrop();
        },
        
        setupDragAndDrop: function() {
            var self = this;
            
            // Make all cards draggable
            var cards = document.querySelectorAll('.kanban-card');
            cards.forEach(function(card) {
                card.setAttribute('draggable', 'true');
                
                card.addEventListener('dragstart', function(e) {
                    self.onDragStart(e, this);
                });
                
                card.addEventListener('dragend', function(e) {
                    self.onDragEnd(e, this);
                });
            });
            
            // Setup drop zones
            this.columns.forEach(function(column) {
                column.addEventListener('dragover', function(e) {
                    self.onDragOver(e, this);
                });
                
                column.addEventListener('drop', function(e) {
                    self.onDrop(e, this);
                });
                
                column.addEventListener('dragleave', function(e) {
                    self.onDragLeave(e, this);
                });
            });
        },
        
        onDragStart: function(e, element) {
            this.draggedElement = element;
            this.draggedFrom = element.parentElement;
            
            element.classList.add('kanban-card-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', element.innerHTML);
            
            // Create placeholder
            this.placeholder = document.createElement('div');
            this.placeholder.className = 'kanban-card-placeholder';
            this.placeholder.style.height = element.offsetHeight + 'px';
        },
        
        onDragEnd: function(e, element) {
            element.classList.remove('kanban-card-dragging');
            
            if (this.placeholder && this.placeholder.parentNode) {
                this.placeholder.parentNode.removeChild(this.placeholder);
            }
            
            // Remove all drag-over classes
            this.columns.forEach(function(column) {
                column.classList.remove('kanban-column-drag-over');
            });
        },
        
        onDragOver: function(e, column) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            
            e.dataTransfer.dropEffect = 'move';
            column.classList.add('kanban-column-drag-over');
            
            // Find the element we're hovering over
            var afterElement = this.getDragAfterElement(column, e.clientY);
            
            if (afterElement == null) {
                column.appendChild(this.placeholder);
            } else {
                column.insertBefore(this.placeholder, afterElement);
            }
            
            return false;
        },
        
        onDragLeave: function(e, column) {
            // Only remove class if we're leaving the column entirely
            if (e.target === column) {
                column.classList.remove('kanban-column-drag-over');
            }
        },
        
        onDrop: function(e, column) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            e.preventDefault();
            column.classList.remove('kanban-column-drag-over');
            
            if (this.draggedElement !== null) {
                // Get new status from column
                var newStatus = column.dataset.status;
                var issueId = this.draggedElement.dataset.issueId;
                
                // Calculate new position
                var cards = Array.from(column.querySelectorAll('.kanban-card:not(.kanban-card-dragging)'));
                var placeholderIndex = Array.from(column.children).indexOf(this.placeholder);
                var newPosition = placeholderIndex;
                
                // Insert the dragged element at the placeholder position
                if (this.placeholder && this.placeholder.parentNode) {
                    column.insertBefore(this.draggedElement, this.placeholder);
                    this.placeholder.parentNode.removeChild(this.placeholder);
                } else {
                    column.appendChild(this.draggedElement);
                }
                
                // Update empty states
                this.updateEmptyStates();
                
                // Send AJAX request to update
                this.updateIssue(issueId, newStatus, newPosition);
            }
            
            return false;
        },
        
        getDragAfterElement: function(column, y) {
            var draggableElements = Array.from(column.querySelectorAll('.kanban-card:not(.kanban-card-dragging)'));
            
            return draggableElements.reduce(function(closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        },
        
        updateIssue: function(issueId, newStatus, newPosition) {
            var self = this;
            var serverUrl = window.location.origin + window.location.pathname;
            
            fetch(serverUrl + '?rex-api-call=issue_tracker_board', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'issue_id=' + issueId + 
                      '&status=' + newStatus + 
                      '&position=' + newPosition + 
                      '&project_id=' + self.projectId
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    console.error('Error updating issue:', data.message);
                    // Reload page on error
                    location.reload();
                } else {
                    // Update card data
                    self.draggedElement.dataset.status = newStatus;
                    self.draggedElement.dataset.sortOrder = newPosition;
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                location.reload();
            });
        },
        
        updateEmptyStates: function() {
            var package_i18n = this.columns[0].querySelector('.kanban-empty') ? 
                this.columns[0].querySelector('.kanban-empty').textContent : 'Keine Issues vorhanden';
            
            this.columns.forEach(function(column) {
                var cards = column.querySelectorAll('.kanban-card');
                var empty = column.querySelector('.kanban-empty');
                
                if (cards.length === 0 && !empty) {
                    var emptyDiv = document.createElement('div');
                    emptyDiv.className = 'kanban-empty';
                    emptyDiv.textContent = package_i18n;
                    column.appendChild(emptyDiv);
                } else if (cards.length > 0 && empty) {
                    empty.remove();
                }
            });
        }
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            IssueTrackerBoard.init();
        });
    } else {
        IssueTrackerBoard.init();
    }
})();
