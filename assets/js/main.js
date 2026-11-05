/**
 * Koperasi Gatra Mandiri - Interactive JavaScript
 */

// Modal Helper Functions (Available immediately globally)
window.openModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
};

window.closeModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
};

// Global click to close modal on backdrop click
document.addEventListener('click', function (e) {
  if (e.target && (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal'))) {
    e.target.classList.remove('active');
    document.body.style.overflow = '';
  }
});

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const sidebar = document.getElementById('sidebar');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
      if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target) && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
      }
    });
  }

  // 3. Table Live Search Filter
  const tableSearchInputs = document.querySelectorAll('[data-table-search]');
  tableSearchInputs.forEach(input => {
    input.addEventListener('input', function () {
      const targetTableId = this.getAttribute('data-table-search');
      const table = document.getElementById(targetTableId);
      if (!table) return;

      const term = this.value.toLowerCase();
      const rows = table.querySelectorAll('tbody tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    });
  });

  // 4. Kalkulator Pinjaman (Simulasi Real-time)
  const loanAmountInput = document.getElementById('calc_jumlah_pinjaman');
  const loanTenorSelect = document.getElementById('calc_tenor');
  const loanInterestInput = document.getElementById('calc_bunga');

  function updateLoanSimulation() {
    if (!loanAmountInput || !loanTenorSelect) return;

    const parseAngka = (val) => {
      if (!val) return 0;
      const clean = val.toString().replace(/[^0-9]/g, '');
      const num = parseFloat(clean);
      return isNaN(num) ? 0 : num;
    };

    const amount = parseAngka(loanAmountInput.value);
    const months = parseInt(loanTenorSelect.value) || 12;
    const rawRate = loanInterestInput ? loanInterestInput.value.toString().replace(',', '.') : '0.8';
    const interestRate = parseFloat(rawRate) || 0.8;

    const principalPerMonth = amount / months;
    const interestPerMonth = amount * (interestRate / 100);
    const totalPerMonth = principalPerMonth + interestPerMonth;
    const grandTotal = totalPerMonth * months;

    const elPokok = document.getElementById('sim_pokok_bulan');
    const elBunga = document.getElementById('sim_bunga_bulan');
    const elTotalBulan = document.getElementById('sim_total_bulan');
    const elGrandTotal = document.getElementById('sim_total_kembali');

    const formatRp = (val) => 'Rp ' + Math.round(val).toLocaleString('id-ID');

    if (elPokok) elPokok.textContent = formatRp(principalPerMonth);
    if (elBunga) elBunga.textContent = formatRp(interestPerMonth);
    if (elTotalBulan) elTotalBulan.textContent = formatRp(totalPerMonth);
    if (elGrandTotal) elGrandTotal.textContent = formatRp(grandTotal);

    // Update hidden inputs if in a form
    const inputAngsuranPokok = document.getElementById('input_angsuran_pokok');
    const inputAngsuranBunga = document.getElementById('input_angsuran_bunga');
    const inputTotalBulanan = document.getElementById('input_total_bulanan');
    const inputTotalHarusDibayar = document.getElementById('input_total_harus_dibayar');

    if (inputAngsuranPokok) inputAngsuranPokok.value = Math.round(principalPerMonth);
    if (inputAngsuranBunga) inputAngsuranBunga.value = Math.round(interestPerMonth);
    if (inputTotalBulanan) inputTotalBulanan.value = Math.round(totalPerMonth);
    if (inputTotalHarusDibayar) inputTotalHarusDibayar.value = Math.round(grandTotal);
  }

  if (loanAmountInput && loanTenorSelect) {
    loanAmountInput.addEventListener('input', updateLoanSimulation);
    loanTenorSelect.addEventListener('input', updateLoanSimulation);
    loanTenorSelect.addEventListener('change', updateLoanSimulation);
    if (loanInterestInput) loanInterestInput.addEventListener('input', updateLoanSimulation);
    updateLoanSimulation();
  }

  // Helper Global Format Rupiah Input
  window.formatRupiahInput = function(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val === '') {
      input.value = '';
      return;
    }
    let sisa = val.length % 3;
    let rupiah = val.substr(0, sisa);
    let ribuan = val.substr(sisa).match(/\d{3}/g);
    if (ribuan) {
      let separator = sisa ? '.' : '';
      rupiah += separator + ribuan.join('.');
    }
    input.value = 'Rp ' + rupiah;
  };

  // Helper Global Format Tanggal DD/MM/YYYY
  window.formatDmyInput = function(input) {
    let v = input.value.replace(/[^0-9]/g, '');
    if (v.length > 8) v = v.substr(0, 8);
    let out = '';
    if (v.length > 4) {
      out = v.substr(0, 2) + '/' + v.substr(2, 2) + '/' + v.substr(4);
    } else if (v.length > 2) {
      out = v.substr(0, 2) + '/' + v.substr(2);
    } else {
      out = v;
    }
    input.value = out;
  };

  // Helper Trigger Calendar Picker
  window.triggerDatePicker = function(btn) {
    const container = btn.closest('.input-date-dmy');
    if (!container) return;
    const nativePicker = container.querySelector('.native-date-hidden');
    if (nativePicker) {
      if (typeof nativePicker.showPicker === 'function') {
        nativePicker.showPicker();
      } else {
        nativePicker.focus();
        nativePicker.click();
      }
    }
  };

  // Helper Sync Native Datepicker to DD/MM/YYYY
  window.syncNativeToDmy = function(picker) {
    const container = picker.closest('.input-date-dmy');
    if (!container) return;
    const dmyInput = container.querySelector('.date-dmy-input');
    if (dmyInput && picker.value) {
      const parts = picker.value.split('-');
      if (parts.length === 3) {
        dmyInput.value = parts[2] + '/' + parts[1] + '/' + parts[0];
      }
    }
  };

  // 5. Quick Fill Demo Login Form
  window.fillDemoLogin = function (username, password) {
    const userField = document.getElementById('username');
    const passField = document.getElementById('password');
    if (userField && passField) {
      userField.value = username;
      passField.value = password;
      userField.focus();
    }
  };

  // 6. Print Trigger Helper
  window.printPage = function () {
    window.print();
  };

  // 7. Enforce Tab Navigation to Skip Calendar Icon Button
  function enforceDatepickerTabindex() {
    document.querySelectorAll('.btn-date-picker').forEach(btn => {
      btn.setAttribute('tabindex', '-1');
    });
    document.querySelectorAll('.native-date-hidden').forEach(picker => {
      picker.setAttribute('tabindex', '-1');
    });
  }
  enforceDatepickerTabindex();

  // 8. Reusable Keyboard Arrow and Enter Navigation for Searchable Dropdowns
  window.initSearchDropdownKeyNav = function(options) {
    const input = (typeof options.input === 'string') ? document.querySelector(options.input) : options.input;
    if (!input) return;

    let activeIdx = -1;

    function getVisibleItems() {
      if (typeof options.getItems === 'function') {
        return options.getItems();
      }
      const list = (typeof options.list === 'string') ? document.querySelector(options.list) : options.list;
      if (!list) return [];
      return Array.from(list.querySelectorAll(options.itemSelector || '.dropdown-item'))
        .filter(el => {
          if (el.style.display === 'none') return false;
          if (el.classList.contains('is-empty-notice') || (el.id && el.id.toLowerCase().includes('empty'))) return false;
          let p = el.parentElement;
          if (p && p.style && p.style.display === 'none') return false;
          return true;
        });
    }

    function highlight(items, index) {
      items.forEach((item, i) => {
        if (i === index) {
          item.classList.add('dropdown-item-active');
          item.scrollIntoView({ block: 'nearest' });
        } else {
          item.classList.remove('dropdown-item-active');
        }
      });
    }

    input.addEventListener('keydown', function(e) {
      const items = getVisibleItems();
      if (!items.length) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = (activeIdx + 1) % items.length;
        highlight(items, activeIdx);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = (activeIdx - 1 + items.length) % items.length;
        highlight(items, activeIdx);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx >= 0 && activeIdx < items.length) {
          if (typeof options.onSelect === 'function') {
            options.onSelect(items[activeIdx]);
          } else {
            items[activeIdx].click();
          }
        } else if (items.length > 0) {
          if (typeof options.onSelect === 'function') {
            options.onSelect(items[0]);
          } else {
            items[0].click();
          }
        }
        activeIdx = -1;
        highlight(items, -1);
      } else if (e.key === 'Escape') {
        activeIdx = -1;
        highlight(items, -1);
        if (typeof options.onEscape === 'function') {
          options.onEscape();
        }
      }
    });

    input.addEventListener('input', function() {
      activeIdx = -1;
      const items = getVisibleItems();
      highlight(items, -1);
    });
  };
});
