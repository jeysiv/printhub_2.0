<?php
/** login.php  -  check the email and password, then go to the right page for that kind of user. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Validator;
use PrintHub\ValidationException;

if (Auth::user() !== null) {
    redirect(Auth::user()->getHomePage());
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();

        $v = new Validator($_POST);
        $v->required('email', 'Email');
        $v->required('password', 'Password');

        if ($v->fails()) {
            $errors = $v->errors();
        } else {
            $user = Auth::login($v->value('email'), $_POST['password']);

            // Send them back to the page they wanted, or to their own home page.
            // getHomePage() is POLYMORPHIC: a Customer and an Admin get different pages.
            $next = $_SESSION['after_login'] ?? $user->getHomePage();
            unset($_SESSION['after_login']);
            flash('success', 'Welcome back, ' . $user->getName() . '!');
            redirect($next);
        }
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
    <h1>Log in</h1>
    <?php errors_render($errors); ?>

    <form class="form narrow" method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button class="btn" type="submit">Log in</button>
    </form>
    <p>New here? <a href="<?= e(url('register.php')) ?>">Create an account</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
