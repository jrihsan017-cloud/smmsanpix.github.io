PANEL FB PRO BOOSTER - PANDUAN PASANG (5 MENIT)

1. Buka index.php dengan teks editor, edit bagian KONFIGURASI paling atas:
   - ADMIN_PASS  : ganti dengan password kuat (WAJIB)
   - BANK_INFO   : rekening/e-wallet tujuan transfer
   - PROVIDER_URL & PROVIDER_KEY : dari panel provider Anda (boleh kosong dulu = proses manual)
2. Upload index.php ke hosting (public_html) atau VPS. Syarat: PHP 8+, ekstensi pdo_sqlite dan curl.
3. Buka domain Anda, login: username "admin" + password yang tadi diisi.
4. Menu Admin > Layanan: atur harga jual, min/max, dan ID layanan provider.
5. Aktifkan SSL (HTTPS) di hosting.

Mode gratis: di index.php ada FREE_MODE (true = semua pesanan gratis tanpa saldo, dengan batas FREE_MAX_QTY dan FREE_PER_DAY; false = berbayar dengan isi saldo).
Alur pelanggan: daftar > isi saldo (transfer) > admin terima > pesan > status otomatis ter-update.
Admin: terima/tolak top-up, ubah saldo, tandai selesai atau batal+refund.
