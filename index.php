<?php
/**
 * index.php  -  the MARKETING FRONT PAGE.
 * Shows: who we are, what we sell, who it is for, promotions, how to order, how to reach us.
 */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Service;
use PrintHub\Promotion;

$pageTitle = 'Home';
$featured  = (new Service())->featured(4);          // first 4 active services from the database
$promos    = (new Promotion())->currentlyActive();  // promotions that are running today

require __DIR__ . '/includes/header.php';
?>

<!-- 1. HERO: identity + value proposition + call to action -->
<section class="hero" aria-labelledby="hero-title">
    <div class="container">
        <h1 id="hero-title">Print it right. Print it fast. Print it at <?= e(SHOP_NAME) ?>.</h1>
        <p><?= e(SHOP_TAGLINE) ?> Upload your file online, pick your options, and pick up your prints when they are ready.</p>
        <div class="actions">
            <a class="btn btn-accent" href="<?= e(url('order.php')) ?>">Order Now</a>
            <a class="btn btn-outline" href="<?= e(url('services.php')) ?>">See Our Services</a>
        </div>
    </div>
</section>

<!-- 2. VALUE PROPOSITION -->
<section class="section" aria-labelledby="why-title">
    <div class="container">
        <h2 id="why-title">Why choose <?= e(SHOP_NAME) ?>?</h2>
        <div class="grid grid-3">
            <div class="card"><h3>Affordable prices</h3><p>Clear prices per page or per piece. No hidden charges, plus regular discounts for students and small businesses.</p></div>
            <div class="card"><h3>Order online</h3><p>Upload your file from home, choose paper size and copies, and track your order status any time.</p></div>
            <div class="card"><h3>Quality you can trust</h3><p>Sharp text, vibrant colors, and durable materials for documents, photos, and tarpaulins.</p></div>
        </div>
    </div>
</section>

<!-- 3. FEATURED SERVICES (read from the database) -->
<section class="section section-alt" aria-labelledby="services-title">
    <div class="container">
        <h2 id="services-title">Popular services</h2>
        <div class="grid grid-4">
            <?php foreach ($featured as $service): ?>
                <article class="card service-card">
                    <span class="badge"><?= e($service['category_name']) ?></span>
                    <h3><?= e($service['service_name']) ?></h3>
                    <p class="service-description"><?= e($service['description']) ?></p>
                    <div class="service-card-footer">
                        <div>
                            <p class="price"><?= money($service['price']) ?></p>
                            <p class="service-unit"><?= e($service['unit']) ?></p>
                        </div>
                        <a class="btn btn-small" href="<?= e(url('order.php?service_id=' . (int)$service['service_id'])) ?>">Order this</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p><a href="<?= e(url('services.php')) ?>">View all services &rarr;</a></p>
    </div>
</section>

<!-- 4. PROMOTIONS (read from the database) -->
<?php if ($promos): ?>
<section class="section" aria-labelledby="promo-title">
    <div class="container">
        <h2 id="promo-title">Current promotions</h2>
        <div class="grid grid-3">
            <?php foreach ($promos as $promo): ?>
                <article class="card promo promo-card">
                    <p class="discount"><?= rtrim(rtrim(number_format((float)$promo['discount'], 2), '0'), '.') ?>% OFF</p>
                    <h3><?= e($promo['title']) ?></h3>
                    <p class="promo-description"><?= e($promo['description']) ?></p>
                    <p class="promo-date">Until <?= e(date('F j, Y', strtotime($promo['end_date']))) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p>Choose a promotion when you place your order.</p>
    </div>
</section>
<?php endif; ?>

<!-- 5. TARGET CUSTOMERS -->
<section class="section section-alt" aria-labelledby="who-title">
    <div class="container">
        <h2 id="who-title">Who we serve</h2>
        <div class="grid grid-3">
            <div class="card"><h3>Students</h3><p>Assignments, research papers, thesis printing, and ID cards.</p></div>
            <div class="card"><h3>Small businesses</h3><p>Business cards, flyers, stickers, and signage to grow your brand.</p></div>
            <div class="card"><h3>Event organizers</h3><p>Tarpaulins, posters, and invitations for birthdays, school events, and more.</p></div>
        </div>
    </div>
</section>

<!-- 6. HOW IT WORKS -->
<section class="section" aria-labelledby="how-title">
    <div class="container">
        <h2 id="how-title">How it works</h2>
        <ol class="steps">
            <li><span><strong>Create an account</strong> or log in.</span></li>
            <li><span><strong>Choose a service</strong>, upload your file, and set paper size and copies.</span></li>
            <li><span><strong>We print it.</strong> Track the status online, then pick up your order.</span></li>
        </ol>
        <a class="btn btn-accent" href="<?= e(url('register.php')) ?>">Get Started</a>
    </div>
</section>

<!-- 7. CONTACT DETAILS -->
<section class="section section-alt" id="contact" aria-labelledby="contact-title">
    <div class="container">
        <h2 id="contact-title">Visit or contact us</h2>
        <div class="grid grid-3">
            <div class="card"><h3>Address</h3><p><?= e(SHOP_ADDRESS) ?></p></div>
            <div class="card"><h3>Call or email</h3><p><?= e(SHOP_PHONE) ?><br><a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a></p></div>
            <div class="card"><h3>Opening hours</h3><p><?= e(SHOP_HOURS) ?></p></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
