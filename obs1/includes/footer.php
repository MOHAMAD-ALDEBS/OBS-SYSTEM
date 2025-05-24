<footer class="dashboard-footer">
        <p>&copy; <?php echo date("Y"); ?> <?php echo $lang['footer_text']; ?></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Language toggle functionality
            const languageToggle = document.getElementById('languageToggle');
            if (languageToggle) {
                languageToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Switch to Turkish
                        window.location.href = '<?php echo $switch_lang_url; ?>';
                    } else {
                        // Switch to English
                        window.location.href = '<?php echo $current_page_url . ($current_query ? "?{$current_query}&lang=en" : "?lang=en"); ?>';
                    }
                });
            }

            // Profile dropdown functionality
            const profileButton = document.getElementById('profileButton');
            const profileDropdown = document.querySelector('.profile-dropdown');
            
            if (profileButton && profileDropdown) {
                profileButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    profileDropdown.classList.toggle('open');
                });
                
                // Close dropdown when clicking elsewhere
                document.addEventListener('click', function(e) {
                    if (!profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('open');
                    }
                });
            }
            
            // Back buttons functionality
            const backButtons = document.querySelectorAll('.back-button');
            backButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    // If the button doesn't have an href or it's just "#"
                    if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                        e.preventDefault();
                        history.back();
                    }
                });
            });
        });
    </script>
    
    <?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>
