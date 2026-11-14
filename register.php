<?php
/** register.php  -  create a customer account. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\User;
use PrintHub\Validator;
use PrintHub\ValidationException;

if (Auth::user() !== null) {
    redirect(Auth::user()->getHomePage());   // already logged in
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();

        // 1. VALIDATE the input on the server
        $v = new Validator($_POST);
        $v->required('name', 'Name');
        $v->maxLength('name', 'Name', 100);
        $v->required('email', 'Email');
        $v->email('email', 'Email');
        $v->maxLength('email', 'Email', 100);
        $v->minLength('password', 'Password', 8);
        $v->matches('password', 'confirm', 'The two passwords do not match.');

        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            // 2. SAVE (the User class hashes the password and checks for duplicate emails)
            (new User())->register($v->value('name'), $v->value('email'), $_POST['password']);
            flash('success', 'Account created! You can log in now.');
            redirect('login.php');
        }
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
    <h1>Create your account</h1>
    <?php errors_render($errors); ?>

    <form class="form narrow" method="post" action="<?= e(url('register.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= old('name') ?>" maxlength="100" required autocomplete="name">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" maxlength="100" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
        <p class="hint">At least 8 characters.</p>

        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" required autocomplete="new-password">

        <button class="btn" type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
