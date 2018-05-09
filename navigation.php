<?php
if (!isset($page_title)) {
    header('Location: error.php');
    exit(1);
}
if (isset($_GET['actn'])) {
    if ($_GET['actn'] == 'login') {
        tools::login(true);
    } elseif ($_GET['actn'] == 'logout') {
        tools::logout();
    }
}
$domain = tools::$options['domain'];
$saved_cart_items = tools::getCart();
$cart_count=count($saved_cart_items);

$links = $links_right = '';
$all_links = array("Products","Cart");
foreach ($all_links as $l) {
    $link = strtolower(str_replace(' ', '_', $l));
    $active = ($page_title==$l) ? "class='active'" : '';
    $c = ($l=="Cart") ? "<span class='badge cart-count' id='comparison-count'>{$cart_count}</span>" : '';
    $links .= "<li {$active}><a href='{$link}.php'>{$l} {$c}</a></li>";
}
if ($page_title=="Complete Order") $links = "<li class='active'><a href='#'>Complete</a></li>";

if ($isAdmin) {
    $admin_links = array('Order Management','Out of Stock');
    foreach ($admin_links as $l) {
        $link = strtolower(str_replace(' ', '_', $l));
        $active = ($page_title==$l) ? "class='active'" : '';
        $links_right .= "<li {$active}><a href='{$link}.php'>{$l}</a></li>";
    }
}
$actn = ($isAdmin) ? 'logout' : 'login';
$log_icon = str_replace('log', 'log-', $actn);
$actn_html = ucfirst($actn);
$links_right .= "<li><a href='{$_SERVER['PHP_SELF']}?actn={$actn}'><span class='glyphicon glyphicon-{$log_icon}'></span> {$actn_html}</a></li>";
?>
<!-- navbar -->
<div class="navbar navbar-default navbar-fixed-top" role="navigation">
    <div class="container">

        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand"><?php echo $domain ?></a>
        </div>
        <div class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <?php echo $links ?>
            </ul>
            <ul class="nav navbar-nav navbar-right">
              <li class="dropdown">
                <a class="dropdown-toggle" data-toggle="dropdown" href="#">Admin
                <span class="caret"></span></a>
                <ul class="dropdown-menu">
                    <?php echo $links_right ?>
                </ul>
              </li>
            </ul>
        </div><!--/.nav-collapse -->

    </div>
</div>
<!-- /navbar -->
