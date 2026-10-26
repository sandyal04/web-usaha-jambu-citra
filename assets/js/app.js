/**
 * JavaScript Utama - Sistem Informasi Usaha Tani Jambu Citra
 * Interaktivitas Form, Kalkulasi Dinamis Keuangan, dan UI Helper
 */

document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. Mobile Sidebar Toggle & Overlay
    // -------------------------------------------------------------
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    let overlay = document.querySelector('.sidebar-overlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // -------------------------------------------------------------
    // 1b. Scroll ke atas saat berpindah halaman via menu sidebar
    // Saat link sidebar diklik → main-content langsung scroll ke atas
    // -------------------------------------------------------------
    document.querySelectorAll('#sidebar .sidebar-link, #sidebar .sidebar-footer-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Tutup sidebar mobile jika terbuka
            if (sidebar && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
            // Scroll halaman ke atas (smooth)
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (mainContent) {
                mainContent.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    // -------------------------------------------------------------
    // 2. Kalkulasi Otomatis Biaya Operasional (Costs Form)
    // Rumus: Total Biaya = Kuantitas x Harga Satuan
    // -------------------------------------------------------------
    const costQtyInput = document.getElementById('cost_quantity');
    const costPriceInput = document.getElementById('cost_unit_price');
    const costTotalInput = document.getElementById('cost_total');

    function calculateCostTotal() {
        if (!costQtyInput || !costPriceInput || !costTotalInput) return;
        const qty = parseFloat(costQtyInput.value) || 0;
        const price = parseFloat(costPriceInput.value) || 0;
        if (qty > 0 && price > 0) {
            costTotalInput.value = Math.round(qty * price);
        }
    }

    if (costQtyInput && costPriceInput) {
        costQtyInput.addEventListener('input', calculateCostTotal);
        costPriceInput.addEventListener('input', calculateCostTotal);
    }

    // -------------------------------------------------------------
    // 3. Kalkulasi Otomatis Penjualan Jambu Citra (Sales Form)
    // Multi-item row, Subtotal = Berat (Kg) x Harga per Kg
    // Total Transaksi = Jumlah Subtotal
    // -------------------------------------------------------------
    const saleItemsTable = document.getElementById('sale-items-table');
    const addItemBtn = document.getElementById('add-item-btn');
    const saleGrandTotalInput = document.getElementById('sale-grand-total');

    function calculateSaleTotals() {
        if (!saleItemsTable) return;
        let grandTotal = 0;
        const rows = saleItemsTable.querySelectorAll('tbody tr.item-row');

        rows.forEach(function (row) {
            const weightInput = row.querySelector('.item-weight');
            const priceInput = row.querySelector('.item-price');
            const subtotalInput = row.querySelector('.item-subtotal');

            const weight = parseFloat(weightInput?.value) || 0;
            const price = parseFloat(priceInput?.value) || 0;
            const subtotal = Math.round(weight * price);

            if (subtotalInput) {
                subtotalInput.value = subtotal;
            }
            grandTotal += subtotal;
        });

        if (saleGrandTotalInput) {
            saleGrandTotalInput.value = grandTotal;
        }

        const grandTotalDisplay = document.getElementById('grand-total-display');
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(grandTotal);
        }
    }

    if (saleItemsTable) {
        saleItemsTable.addEventListener('input', function (e) {
            if (e.target.classList.contains('item-weight') || e.target.classList.contains('item-price')) {
                calculateSaleTotals();
            }
        });

        saleItemsTable.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.remove-item-btn');
            if (removeBtn) {
                const rows = saleItemsTable.querySelectorAll('tbody tr.item-row');
                if (rows.length > 1) {
                    removeBtn.closest('tr').remove();
                    calculateSaleTotals();
                } else {
                    alert('Transaksi penjualan harus memiliki minimal satu item produk.');
                }
            }
        });
    }

    if (addItemBtn && saleItemsTable) {
        addItemBtn.addEventListener('click', function () {
            const tbody = saleItemsTable.querySelector('tbody');
            const newRow = document.createElement('tr');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <td>
                    <select name="items[product_grade][]" class="form-select" required>
                        <option value="Jambu Citra Grade Super">Jambu Citra Grade Super</option>
                        <option value="Jambu Citra Grade A" selected>Jambu Citra Grade A</option>
                        <option value="Jambu Citra Grade B">Jambu Citra Grade B</option>
                        <option value="Jambu Citra Grade Standar">Jambu Citra Grade Standar</option>
                        <option value="Jambu Citra Sortiran (BS)">Jambu Citra Sortiran (BS)</option>
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0.01" name="items[weight_kg][]" class="form-control item-weight" placeholder="0" required>
                        <span class="input-group-text">Kg</span>
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="1" min="0" name="items[price_per_kg][]" class="form-control item-price" placeholder="0" required>
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="1" name="items[subtotal][]" class="form-control item-subtotal bg-light" readonly>
                    </div>
                </td>
                <td>
                    <input type="text" name="items[notes][]" class="form-control" placeholder="Catatan item (opsional)">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" title="Hapus Baris">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
        });
    }

    // -------------------------------------------------------------
    // 4. Konfirmasi Penghapusan Data (Security & Accidental Prevention)
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-confirm-delete').forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = button.getAttribute('data-message') || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // -------------------------------------------------------------
    // 5. Inisialisasi Tooltip Bootstrap (jika Bootstrap JS aktif)
    // -------------------------------------------------------------
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
