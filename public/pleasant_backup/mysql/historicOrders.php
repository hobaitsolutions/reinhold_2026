<?php
require_once('dbHandler.php');
$db     = new MySQLDBInterface();

$orders = $db->getHistoricOrdersByCustomerId('31897');

foreach ($orders as $order):
	$products = json_decode($order['products']);
	?>
    <div class="table order-table" data-order-detail-loader="true">
        <div class="order-wrapper">
            <div class="order-item-header">
                <div class="row flex-wrap">
                    <h5 class="col-12 order-table-header-heading">Bestellung: <?php echo $order['documentnumber'] ?></h5>
                    <div class="col-12 order-table-header-order-number">
                        <span class="order-table-header-label">Bestellnummer:</span>
                        <span class="order-table-body-value"><?php echo $order['date'] ?></span>
                    </div>
                    <div class="col-12 order-table-header-order-status">
                        <h5>
                            Status:
                            abgeschlossen
                        </h5>
                    </div>
                </div>
                <div class="row">
                    <div class="col p-0">
                        <a href="/customAPI/order-datasheets.php?orderId=d911e8be86e048ab80d701724f46c368"
                           class="btn btn-sm btn-primary">Datenblätter herunterladen</a>
                    </div>
                    <div class="col p-0 text-right">
                        <button class="btn btn-light btn-sm order-hide-btn collapsed" type="submit"
                                data-toggle="collapse"
                                data-target="#hist-<?php echo $order['documentnumber'] ?>" aria-expanded="false" aria-controls="collapseExample">
                            <span class="order-view-btn-text">Anzeigen</span>
                            <span class="order-hide-btn-text">Verstecken</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="order-item-detail">
            <div class="collapse" id="hist-<?php echo $order['documentnumber']; ?>">
                <div class="order-detail-content">
                    <div class="order-detail-content-body">
                    </div>
                    <div class="order-detail-content-header">
                        <div class="row">
                            <div class="col-2 order-detail-content-header-cell order-header-name">
                                Artikelnummer
                            </div>
                            <div class="col-8 order-detail-content-header-cell order-header-quantity">
                                Produkt
                            </div>
                            <div class="col-2 order-detail-content-header-cell order-header-price">
                                Anzahl
                            </div>
                        </div>
                    </div>
                    <div class="order-detail-content-body">
                        <div class="order-detail-content-body">
							<?php
							foreach ($products as $product):
								?>
                                <div class="row order-detail-content-row">
                                    <div class="col-12 col-md-2 order-item order-item-number">
										<?php echo $product->articleNumber ?>
                                    </div>
                                    <div class="col-12 col-md-8 order-item order-item-name">
                                        <a> <strong class="name-value"><?php echo $product->name ?></strong></a>
                                    </div>
                                    <div class="col-12 col-md-2 order-item order-item-quantity">
										<?php echo $product->quantity ?>
                                    </div>
                                </div>
                                <div class="order-item-detail-list-item-divider"></div>
							<?php
							endforeach; ?>
                            <div class="order-detail-content-footer">
                                <div class="order-item-detail-footer">
                                    <div class="row no-gutters">
                                        <div class="col-12 col-md-7 col-xl-6">
                                            <dl class="row no-gutters order-item-detail-labels">
                                                <dt class="col-6 col-md-5">Bestelldatum:</dt>
                                                <dd class="col-6 col-md-7 order-item-detail-labels-value"><?php echo $order['date'] ?></dd>
                                                <dt class="col-6 col-md-5">Bestellnummer:</dt>
                                                <dd class="col-6 col-md-7 order-item-detail-labels-value"><?php echo $order['documentnumber'] ?><</dd>
                                            </dl>
                                        </div>
                                        <div class="col-12 col-md-5 col-xl-6">
                                            <dl class="row no-gutters order-item-detail-summary">
                                                <dt class="col-6 col-md-8">
                                                    Gesamtsumme:
                                                </dt>
                                                <dd class="col-6 col-md-4">
	                                                <?php echo $order['total'] ?><
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
endforeach;
?>



