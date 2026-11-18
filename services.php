<?php
/** Public services and prices menu. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Service;

$pageTitle = 'Services and Prices';
$bodyClass = 'services-page';
$categoryMeta = [
    'Business Printing' => ['number' => '01', 'accent' => '#5B91A3'],
    'Custom Printing'   => ['number' => '02', 'accent' => '#2F4A63'],
    'Document Printing' => ['number' => '03', 'accent' => '#CFEAE0'],
    'Large Format'      => ['number' => '04', 'accent' => '#5B91A3'],
    'Marketing'         => ['number' => '05', 'accent' => '#2F4A63'],
    'Photo Printing'    => ['number' => '06', 'accent' => '#CFEAE0'],
    'School Printing'   => ['number' => '07', 'accent' => '#5B91A3'],
];

$byCategory = [];
foreach ((new Service())->allActive() as $service) {
    $byCategory[$service['category_name']][] = $service;
}

require __DIR__ . '/includes/header.php';
?>
<div id="services-main" class="services-container services-main" tabindex="-1">
    <section class="services-intro" aria-labelledby="services-title">
        <div class="services-intro-heading">
            <p class="services-eyebrow">Services &amp; Pricing</p>
            <h1 id="services-title">Our services<br class="desktop-break"> and prices</h1>
        </div>
        <p class="services-intro-copy">Pick a service and place your order online. Prices are in Philippine pesos.</p>
    </section>

    <div class="services-category-list">
        <?php foreach ($categoryMeta as $category => $meta): ?>
            <?php $items = $byCategory[$category] ?? []; if (!$items) { continue; } ?>
            <section class="services-category" aria-labelledby="category-<?= e($meta['number']) ?>" style="--category-accent: <?= e($meta['accent']) ?>;">
                <div class="services-category-rail">
                    <span class="category-dot" aria-hidden="true"></span>
                    <span class="category-number"><?= e($meta['number']) ?></span>
                    <h2 id="category-<?= e($meta['number']) ?>"><?= e($category) ?></h2>
                </div>
                <div class="service-card-grid">
                    <?php foreach ($items as $service): ?>
                        <article class="service-menu-card">
                            <h3><?= e($service['service_name']) ?></h3>
                            <p class="service-menu-description"><?= e($service['description']) ?></p>
                            <div class="service-menu-footer">
                                <div class="service-menu-price">
                                    <p class="service-menu-amount"><?= money($service['price']) ?></p>
                                    <span class="service-menu-unit"><?= e($service['unit']) ?></span>
                                </div>
                                <a class="service-order-link" href="<?= e(url('order.php')) ?>" aria-label="Order <?= e($service['service_name']) ?>">
                                    <span>Order this</span>
                                    <svg aria-hidden="true" focusable="false" viewBox="0 0 20 20"><path d="M3.5 10h12m-5-5 5 5-5 5"/></svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
