                            <div class="col-md-3">
                                <h6 class="mb-2">Order Status</h6>
                                <?php
                                $status_class = match($order['status']) {
                                    'Pending' => 'status-pending',
                                    'Processing' => 'status-processing',
                                    'Shipped' => 'status-shipped',
                                    'Delivered' => 'status-delivered',
                                    'Cancelled' => 'status-cancelled',
                                    default => ''
                                };
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <p class="mt-2 mb-0">
                                        <small class="text-muted">
                                            Tracking #: <?php echo htmlspecialchars($order['tracking_number']); ?>
                                        </small>
                                    </p>
                                <?php endif; ?>
                            </div> 