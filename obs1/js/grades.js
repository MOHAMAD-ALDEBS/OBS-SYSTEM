/**
 * JavaScript functionality for grade management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit the course selection form when a course is selected
    const courseSelect = document.getElementById('course-select');
    if (courseSelect) {
        courseSelect.addEventListener('change', function() {
            document.getElementById('course-select-form').submit();
        });
    }
    
    // Calculate totals and letter grades dynamically as instructor enters grades
    const gradeInputs = document.querySelectorAll('.grade-input');
    if (gradeInputs.length > 0) {
        gradeInputs.forEach(input => {
            input.addEventListener('change', function() {
                calculateGrades(this);
            });
        });
    }
    
    // Function to calculate total and letter grade
    function calculateGrades(changedInput) {
        // Find the row containing the changed input
        const row = changedInput.closest('tr');
        if (!row) return;
        
        // Get all grade inputs in this row
        const midtermInput = row.querySelector('input[name="midterm[]"]');
        const finalInput = row.querySelector('input[name="final[]"]');
        const assignmentInput = row.querySelector('input[name="assignment[]"]');
        
        // Get current values
        const midterm = midtermInput?.value ? parseFloat(midtermInput.value) : null;
        const final = finalInput?.value ? parseFloat(finalInput.value) : null;
        const assignment = assignmentInput?.value ? parseFloat(assignmentInput.value) : null;
        
        // Find the cells for total and letter grade
        const totalCell = row.cells[row.cells.length - 2];
        const letterCell = row.cells[row.cells.length - 1];
        
        // Only calculate if all components are present
        if (midterm !== null && final !== null && assignment !== null) {
            // Calculate total grade (40% midterm + 50% final + 10% assignment)
            const totalGrade = (midterm * 0.4) + (final * 0.5) + (assignment * 0.1);
            
            // Update total cell
            totalCell.textContent = totalGrade.toFixed(1);
            
            // Determine letter grade
            let letterGrade = 'F';
            if (totalGrade >= 90) {
                letterGrade = 'A';
            } else if (totalGrade >= 85) {
                letterGrade = 'A-';
            } else if (totalGrade >= 80) {
                letterGrade = 'B+';
            } else if (totalGrade >= 75) {
                letterGrade = 'B';
            } else if (totalGrade >= 70) {
                letterGrade = 'B-';
            } else if (totalGrade >= 65) {
                letterGrade = 'C+';
            } else if (totalGrade >= 60) {
                letterGrade = 'C';
            } else if (totalGrade >= 55) {
                letterGrade = 'C-';
            } else if (totalGrade >= 50) {
                letterGrade = 'D+';
            } else if (totalGrade >= 45) {
                letterGrade = 'D';
            }
            
            // Update letter grade cell
            letterCell.textContent = letterGrade;
        } else {
            // If any component is missing, show dash
            totalCell.textContent = '-';
            letterCell.textContent = '-';
        }
    }
});
