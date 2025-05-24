/**
 * Form validation script for login and registration
 */

document.addEventListener('DOMContentLoaded', function() {
    // Login form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate ID number
            const loginId = document.getElementById('login_id');
            if (loginId.value.trim() === '') {
                showError(loginId, langStrings.id_number_required || 'ID number is required');
                isValid = false;
            } else {
                clearError(loginId);
            }
            
            // Validate password
            const password = document.getElementById('password');
            if (password.value === '') {
                showError(password, langStrings.password_required || 'Password is required');
                isValid = false;
            } else {
                clearError(password);
            }
            
            // Validate user type
            const userType = document.getElementById('user_type');
            if (!userType.value) {
                showError(userType, langStrings.user_type_required || 'User type is required');
                isValid = false;
            } else {
                clearError(userType);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Registration form validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate name
            const name = document.getElementById('name');
            if (name.value.trim() === '') {
                showError(name, langStrings.name_required || 'Name is required');
                isValid = false;
            } else {
                clearError(name);
            }
            
            // Validate email
            const email = document.getElementById('email');
            if (email.value.trim() === '') {
                showError(email, langStrings.email_required || 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showError(email, langStrings.email_invalid || 'Please enter a valid email');
                isValid = false;
            } else {
                clearError(email);
            }
            
            // Validate username
            const username = document.getElementById('username');
            if (username.value.trim() === '') {
                showError(username, langStrings.username_required || 'Username is required');
                isValid = false;
            } else if (username.value.trim().length < 4) {
                showError(username, langStrings.username_min_length || 'Username must be at least 4 characters');
                isValid = false;
            } else {
                clearError(username);
            }
            
            // Validate password
            const password = document.getElementById('password');
            if (password.value === '') {
                showError(password, langStrings.password_required || 'Password is required');
                isValid = false;
            } else if (password.value.length < 6) {
                showError(password, langStrings.password_min_length || 'Password must be at least 6 characters');
                isValid = false;
            } else {
                clearError(password);
            }
            
            // Validate confirm password
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value === '') {
                showError(confirmPassword, langStrings.password_required || 'Please confirm your password');
                isValid = false;
            } else if (confirmPassword.value !== password.value) {
                showError(confirmPassword, langStrings.passwords_dont_match || 'Passwords do not match');
                isValid = false;
            } else {
                clearError(confirmPassword);
            }
            
            // Validate user type is selected
            const userType = document.getElementById('user_type');
            if (!userType.value) {
                showError(userType, langStrings.user_type_required || 'Please select a user type');
                isValid = false;
            } else {
                clearError(userType);
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Helper functions
    function showError(input, message) {
        const formGroup = input.parentElement;
        const errorElement = formGroup.querySelector('.error-text');
        
        input.classList.add('error');
        errorElement.textContent = message;
    }
    
    function clearError(input) {
        const formGroup = input.parentElement;
        const errorElement = formGroup.querySelector('.error-text');
        
        input.classList.remove('error');
        errorElement.textContent = '';
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
