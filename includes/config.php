<?php
/**
 * config.php  -  every setting you might want to change lives here.
 * A "constant" (const) is a value that never changes while the site runs.
 */

// ---- Database (these are the XAMPP defaults) ------------------------------
const DB_HOST = 'localhost';
const DB_NAME = 'printhub';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP's root user has no password by default

// ---- Website --------------------------------------------------------------
const BASE_URL = '/printhub'; // the folder name inside C:\xampp\htdocs
const DEBUG    = true;        // true = show error details. Set to false for a "real" site.

// ---- Shop identity (EDIT THESE: they appear on the front page) -----------
const SHOP_NAME    = 'PrintHub';
const SHOP_TAGLINE = 'Fast, affordable printing for students, businesses, and events.';
const SHOP_ADDRESS = '#743 Brgy. Lanas, Mangaldan, Pangasinan';
const SHOP_PHONE   = '0915 693 3894';
const SHOP_EMAIL   = 'printhub@gmail.com';
const SHOP_HOURS   = 'Monday to Sunday, 8:00 AM to 6:00 PM';
