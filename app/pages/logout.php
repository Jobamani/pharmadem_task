<?php
if (is_post()) {
    verify_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
}
redirect('login');
