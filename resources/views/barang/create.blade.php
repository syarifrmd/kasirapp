@extends('layouts.kasir')
@section('title','Tambah Barang')
@section('content')
<h1 class="text-xl font-semibold mb-4">Tambah Barang</h1>
<form action="{{ route('barang.store') }}" method="POST" class="space-y-4 bg-white p-6 rounded shadow max-w-3xl" x-data="barangForm()">
    @csrf
    <!-- Pilih Kategori -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <h3 class="font-semibold text-blue-900 mb-3">Pilih Kategori</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Kategori</label>
                <select name="kategori" x-model="selectedKategori" @change="kategoriChanged()" class="w-full border rounded px-3 py-2 text-sm" required>
                    @foreach(\App\Models\Barang::KATEGORI as $k => $label)
                        <option value="{{ $k }}" {{ old('kategori') == $k ? 'selected' : '' }}>{{ $label }} ({{ $k }})</option>
                    @endforeach
                    <option value="CUSTOM">Buat Kategori Baru</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nama Merk Barang</label>
                <input type="text" name="merk" value="{{ old('merk') }}" class="w-full border rounded px-3 py-2 text-sm" required placeholder="Contoh: Aqua, Indomie">
            </div>
        </div>
    </div>
    
    <!-- Form Kategori Baru (tampil jika pilih CUSTOM) -->
    <div x-show="selectedKategori === 'CUSTOM'" x-transition class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
        <h3 class="font-semibold text-green-900 mb-3">Buat Kategori Baru</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1 text-green-900">Kode Kategori <span class="text-red-500">*</span></label>
                <input type="text" name="kategori" x-model="customKategoriCode" placeholder="Masukkan 2 huruf kapital, misal: PR" maxlength="2" pattern="[A-Z]{2}" class="w-full border-2 border-green-300 rounded px-3 py-2 text-sm font-mono uppercase focus:border-green-500 focus:ring-2 focus:ring-green-200" :required="selectedKategori === 'CUSTOM'">
                <p class="text-xs text-gray-600 mt-1">Hanya 2 huruf kapital (A-Z)</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1 text-green-900">Nama Kategori <span class="text-gray-400">(opsional)</span></label>
                <input type="text" name="kategori_nama" x-model="customKategoriNama" placeholder="Contoh: Perlengkapan" class="w-full border-2 border-green-300 rounded px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200">
                <p class="text-xs text-gray-600 mt-1">Untuk keterangan saja</p>
            </div>
        </div>
        <div class="mt-3 bg-white border border-green-200 rounded p-3">
            <p class="text-xs font-semibold text-green-900 mb-1">Contoh Kategori Baru:</p>
            <ul class="text-xs text-gray-700 space-y-1">
                <li>• <span class="font-mono font-bold">PR</span> - Perlengkapan Rumah</li>
                <li>• <span class="font-mono font-bold">AP</span> - Alat Pertanian</li>
                <li>• <span class="font-mono font-bold">KS</span> - Kosmetik</li>
                <li>• <span class="font-mono font-bold">EL</span> - Elektronik</li>
            </ul>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Deskripsi</label>
        <textarea name="deskripsi" rows="2" class="w-full border rounded px-3 py-2 text-sm" placeholder="Deskripsi umum merk barang">{{ old('deskripsi') }}</textarea>
    </div>
    
    <hr class="my-4">
    <div class="flex justify-between items-center mb-2">
        <h3 class="font-medium">Varian Jenis & Kemasan</h3>
        <button type="button" @click="addVarian()" class="text-sm px-3 py-1 bg-green-600 text-white rounded">+ Tambah Varian</button>
    </div>
    
    <div class="space-y-3">
        <template x-for="(varian, index) in varians" :key="index">
            <div class="border p-3 rounded bg-gray-50">
                <div class="grid grid-cols-12 gap-3 items-end mb-2">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium mb-1">Nama Jenis</label>
                        <input type="text" :name="'varians['+index+'][jenis]'" x-model="varian.jenis" class="w-full border rounded px-2 py-1 text-sm" required placeholder="Botol">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium mb-1">Kode Jenis</label>
                        <input type="text" :name="'varians['+index+'][kode_jenis]'" x-model="varian.kode_jenis" class="w-full border rounded px-2 py-1 text-sm" maxlength="2" placeholder="01 (auto)">
                        <p class="text-xs text-gray-400">Kosongkan = 01</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium mb-1">Kode Kemasan</label>
                        <input type="text" :name="'varians['+index+'][kode_kemasan]'" x-model="varian.kode_kemasan" class="w-full border rounded px-2 py-1 text-sm" maxlength="2" placeholder="01">
                        <p class="text-xs text-gray-400">2 digit (01-99)</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium mb-1">Ukuran/Kemasan</label>
                        <input type="text" :name="'varians['+index+'][ukuran_kemasan]'" x-model="varian.ukuran_kemasan" class="w-full border rounded px-2 py-1 text-sm" required placeholder="600ml">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium mb-1">Harga</label>
                        <input type="number" :name="'varians['+index+'][harga_barang]'" x-model="varian.harga_barang" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-medium mb-1">Stok</label>
                        <input type="number" :name="'varians['+index+'][stok_barang]'" x-model="varian.stok_barang" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                    <div class="col-span-1">
                        <button type="button" @click="removeVarian(index)" class="w-full px-2 py-1 bg-red-600 text-white rounded text-xs">Hapus</button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" x-model="varian.manual_kode" class="rounded">
                        <span>Klik untuk Kode Barang Manual</span>
                    </label>
                </div>
                <div x-show="varian.manual_kode" class="mt-2">
                    <label class="block text-xs font-medium mb-1">Kode Barang (8 digit)</label>
                    <input type="text" :name="'varians['+index+'][kode_barang_manual]'" x-model="varian.kode_barang_manual" class="w-full border rounded px-2 py-1 text-sm font-mono" maxlength="8" placeholder="MN010101">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan untuk auto-generate</p>
                </div>
            </div>
        </template>
        <p x-show="varians.length === 0" class="text-sm text-gray-500 text-center py-4">Belum ada varian. Klik tombol "Tambah Varian" untuk menambahkan.</p>
    </div>
    
    <!-- Inisialisasi Stok Opsional -->
    <div class="bg-white p-4 rounded shadow max-w-3xl mt-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold">Inisialisasi Stok (Opsional, Berlaku untuk semua varian)</h3>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="init_stock" value="1" x-model="init_stock" class="rounded"> Aktifkan
            </label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3" x-show="init_stock">
            <div>
                <label class="block text-xs font-medium mb-1">Vendor</label>
                <select name="vendor_init_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">-- Pilih Vendor --</option>
                    @foreach(\App\Models\Vendor::orderBy('nama')->get() as $v)
                        <option value="{{ $v->id }}">{{ $v->kode ?? 'NO-CODE' }} • {{ $v->nama }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Atau isi vendor baru di bawah</p>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Vendor Baru</label>
                <input type="text" name="vendor_init_baru" class="w-full border rounded px-3 py-2 text-sm" placeholder="Nama vendor baru">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Kode Vendor (opsional)</label>
                <input type="text" name="vendor_init_kode" class="w-full border rounded px-3 py-2 text-sm" placeholder="VND-XXXX (otomatis jika kosong)">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Alamat Vendor</label>
                <input type="text" name="vendor_init_alamat" class="w-full border rounded px-3 py-2 text-sm" placeholder="Alamat vendor">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">No Kontak</label>
                <input type="text" name="vendor_init_no_kontak" class="w-full border rounded px-3 py-2 text-sm" placeholder="0812xxxx">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Nama Sales</label>
                <input type="text" name="vendor_init_nama_sales" class="w-full border rounded px-3 py-2 text-sm" placeholder="Nama sales">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">HPP/Unit</label>
                <input type="number" step="0.01" name="unit_cost_init" class="w-full border rounded px-3 py-2 text-sm" placeholder="0">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Harga Jual Baru (opsional)</label>
                <input type="number" step="0.01" name="harga_jual_baru_init" class="w-full border rounded px-3 py-2 text-sm" placeholder="Biarkan kosong jika tidak berubah">
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">Catatan: Qty awal diambil dari kolom "Stok" pada masing-masing varian.</p>
    </div>

    <div class="flex gap-2 pt-4">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Simpan</button>
        <a href="{{ route('barang.index') }}" class="px-4 py-2 bg-gray-200 rounded text-sm">Batal</a>
    </div>
</form>

<script>
function barangForm() {
    return {
        init_stock: false,
        selectedKategori: '{{ old("kategori", "RK") }}',
        customKategoriCode: '',
        customKategoriNama: '',
        varians: [{jenis: '', kode_jenis: '01', kode_kemasan: '01', ukuran_kemasan: '', harga_barang: 0, stok_barang: 0, manual_kode: false, kode_barang_manual: ''}],
        kategoriChanged() {
            if (this.selectedKategori !== 'CUSTOM') {
                this.customKategoriCode = '';
                this.customKategoriNama = '';
            }
        },
        addVarian() {
            // Find max kode_jenis and kode_kemasan for auto increment
            let maxJenis = 0;
            let maxKemasan = 0;
            
            this.varians.forEach(v => {
                let jenis = parseInt(v.kode_jenis || '0');
                let kemasan = parseInt(v.kode_kemasan || '0');
                if (jenis > maxJenis) maxJenis = jenis;
                if (kemasan > maxKemasan) maxKemasan = kemasan;
            });
            
            let nextJenis = String(maxJenis).padStart(2, '0');
            let nextKemasan = String(maxKemasan + 1).padStart(2, '0');
            
            this.varians.push({
                jenis: '', 
                kode_jenis: nextJenis, 
                kode_kemasan: nextKemasan, 
                ukuran_kemasan: '', 
                harga_barang: 0, 
                stok_barang: 0, 
                manual_kode: false, 
                kode_barang_manual: ''
            });
        },
        removeVarian(index) {
            if (this.varians.length > 1) {
                this.varians.splice(index, 1);
            } else {
                alert('Minimal harus ada 1 varian');
            }
        }
    }
}
</script>
@endsection
