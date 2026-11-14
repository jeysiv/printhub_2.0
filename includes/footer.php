</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p><strong><?= e(SHOP_NAME) ?></strong> &middot; <?= e(SHOP_ADDRESS) ?></p>
        <p><?= e(SHOP_PHONE) ?> &middot; <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a></p>
        <p class="muted">&copy; <?= date('Y') ?> <?= e(SHOP_NAME) ?>. School project.</p>
    </div>
</footer>
<script>
document.querySelectorAll('.flash-messages .alert').forEach(function (alert) {
    window.setTimeout(function () {
        alert.classList.add('alert-dismissing');
        window.setTimeout(function () { alert.remove(); }, 350);
    }, 3000);
});
</script>
</body>
</html>
