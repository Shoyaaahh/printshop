<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) audit('logout', 'user', current_user()['id']);
logout_user();
session_start();
flash('success', 'You have been logged out.');
redirect('index.php');
