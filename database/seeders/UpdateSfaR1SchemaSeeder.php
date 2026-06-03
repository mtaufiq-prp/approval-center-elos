<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\TblDocumentType;

class UpdateSfaR1SchemaSeeder extends Seeder {
    public function run(): void {
        $schema = json_decode('[{"field": "header.branch_name", "label": "Cabang", "type": "text", "width": "third"}, {"field": "header.customer_name", "label": "Nama Customer", "type": "text", "width": "third"}, {"field": "header.employee_name", "label": "Salesman", "type": "text", "width": "third"}, {"field": "header.tipe_tagihan", "label": "Jenis Retur", "type": "badge", "width": "third", "colors": {"GANTI BARANG/KLAIM": "primary", "POTONG TAGIHAN": "warning"}}, {"field": "header.alasan_retur", "label": "Alasan Retur", "type": "badge", "width": "third", "colors": {"KEMASAN RUSAK": "danger", "PRODUK CACAT PRODUKSI": "warning", "KUALITAS BURUK": "warning"}}, {"field": "header.jenis_product", "label": "Jenis Produk", "type": "text", "width": "third"}, {"field": "", "label": "Ringkasan Nilai", "type": "separator", "width": "full"}, {"field": "header.nilai_omset", "label": "Total Omset (IDR)", "type": "currency", "width": "third", "prefix": "Rp "}, {"field": "header.nilai_retur", "label": "Total Retur (IDR)", "type": "currency", "width": "third", "prefix": "Rp "}, {"field": "header.nilai_persen", "label": "% Retur vs Omset", "type": "number", "width": "third"}, {"field": "billing.budget", "label": "Budget Retur (IDR)", "type": "currency", "width": "third", "prefix": "Rp "}, {"field": "retur.total_retur", "label": "Akum. Retur (IDR)", "type": "currency", "width": "third", "prefix": "Rp "}, {"field": "header.budget_from", "label": "Periode Budget Dari", "type": "text", "width": "third"}, {"field": "", "label": "Detail Barang", "type": "separator", "width": "full"}, {"field": "detail", "label": "Daftar Barang Retur", "type": "table", "width": "full", "columns": ["product_name", "qty", "uom", "value_retur", "kemasan_produk", "kualitas_produk", "alasan_retur", "mts_mto", "no_batch", "detail_kemasan"], "col_labels": ["Nama Barang", "Qty", "UoM", "Nilai Retur", "Kondisi Kemasan", "Kualitas", "Alasan", "MTS/MTO", "No Batch", "Detail Kemasan"]}, {"field": "", "label": "Riwayat Persetujuan SFA", "type": "separator", "width": "full"}, {"field": "history", "label": "History SFA", "type": "table", "width": "full", "columns": ["xcreated_date", "employee_name", "jobtitlename", "status", "notes"], "col_labels": ["Tanggal", "Nama", "Jabatan", "Status", "Catatan"]}]', true);
        $dt = TblDocumentType::where('doc_code','SFA_R1')->first();
        if (!$dt) {
            $this->command->error('Document Type SFA_R1 tidak ditemukan.');
            return;
        }
        $dt->update(['form_schema' => $schema]);
        $this->command->info('form_schema SFA_R1 updated — ' . count($schema) . ' fields.');
    }
}
