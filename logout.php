<?php
/** logout.php  -  end the session and go back to the front page. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;

Auth::logout();
session_start();                       // a fresh session, only so we can show the message below
flash('success', 'You have been logged out.');
redirect('index.php');
