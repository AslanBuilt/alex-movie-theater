    </main>
</div>

<div class="modal-backdrop" id="deleteModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <h2 id="deleteModalTitle" class="modal-title">Confirm delete</h2>
        <p class="modal-body">
            Are you sure you want to delete
            <strong id="deleteModalName">this item</strong>?
            This action cannot be undone.
        </p>
        <form method="post" id="deleteModalForm" data-prevent-double="1">
            <input type="hidden" name="csrf_token" value="<?= e($auth->generateCsrfToken()) ?>">
            <input type="hidden" name="id" id="deleteModalId" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="js/admin.js" defer></script>
</body>
</html>
