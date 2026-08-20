<?php
/**
 * Telegram Reminder Management System
 * Admin Footer Layout & Modals
 */

declare(strict_types=1);
?>
        </main> <!-- End app-content -->

        <!-- Bottom Footer -->
        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto">
            <div class="container-fluid d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div>
                    &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong>. All rights reserved.
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-secondary border">Core PHP 8.x + MySQL</span>
                    <a href="<?= BASE_URL ?>/admin/settings.php" class="text-decoration-none text-muted"><i class="bi bi-gear me-1"></i>Settings</a>
                </div>
            </div>
        </footer>
    </div> <!-- End app-main -->
</div> <!-- End app-wrapper -->

<!-- Global Modal: Send Direct Test Message -->
<div class="modal fade" id="modalTestMessage" tabindex="-1" aria-labelledby="modalTestMessageLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="formSendTestMsg">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header bg-primary text-white py-3 px-4">
                    <h5 class="modal-title fs-6 fw-bold d-flex align-items-center gap-2" id="modalTestMessageLabel">
                        <i class="bi bi-send-fill"></i> Send Test Telegram Message
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Recipient Name</label>
                        <div class="fw-semibold text-dark fs-6" id="testMsgRecipientName">Recipient</div>
                    </div>
                    <div class="mb-3">
                        <label for="testMsgChatId" class="form-label small fw-bold text-muted">Telegram Chat ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="testMsgChatId" name="chat_id" required placeholder="e.g. 123456789">
                    </div>
                    <div class="mb-3">
                        <label for="testMsgContent" class="form-label small fw-bold text-muted">Message Text (HTML supported)</label>
                        <textarea class="form-control" id="testMsgContent" name="message" rows="3" required placeholder="Test message from Telegram Reminder System...">🔔 <b>Test Notification</b>&#10;This is a direct test message from your Telegram Reminder Management System.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2.5 px-4 border-top">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-send"></i> Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery 3.7.1 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Chart.js for Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Core App Scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

</body>
</html>
