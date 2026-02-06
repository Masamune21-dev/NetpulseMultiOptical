    </main>
</div>

<div class="modal" id="confirmDeleteModal">
    <div class="modal-box" style="max-width:420px;">
        <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        <h3><i class="fas fa-triangle-exclamation"></i> Konfirmasi Hapus</h3>
        <p id="confirmDeleteMessage" style="margin: 1rem 0; color: var(--text-soft);">
            Yakin ingin menghapus data ini?
        </p>
        <div class="modal-actions">
            <button class="btn btn-danger" id="confirmDeleteYes">
                <i class="fas fa-trash"></i> Hapus
            </button>
            <button class="btn btn-outline" onclick="closeDeleteModal()">
                Batal
            </button>
        </div>
    </div>
</div>

<footer class="app-footer">
    <span>© <?= date('Y') ?> NetpulseMultiOptical. Web ini dibuat oleh Masamune.</span>
</footer>

<!-- GLOBAL SCRIPTS -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script src="assets/js/script.js"></script>

</body>
</html>
