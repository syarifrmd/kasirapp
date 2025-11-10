<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use App\Models\TransaksiItem;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function index(Request $request)
    {
        $query = Barang::query();

        // Fast search
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function($w) use ($q){
                $w->where('merk', 'like', "%$q%")
                  ->orWhere('jenis', 'like', "%$q%")
                  ->orWhere('kode_barang', 'like', "%$q%")
                  ->orWhere('ukuran_kemasan', 'like', "%$q%")
                  ->orWhere('deskripsi', 'like', "%$q%");
            });
        }

        // Filter by kategori
        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Availability filter
        if ($request->filled('available')) {
            if ($request->available === 'ada') $query->where('stok_barang', '>', 0);
            if ($request->available === 'habis') $query->where('stok_barang', '<=', 0);
        }

        // Filter by vendor via batches
        if ($request->filled('vendor_id')) {
            $vendorId = (int) $request->vendor_id;
            $query->whereExists(function($sub) use ($vendorId){
                $sub->select(DB::raw(1))
                    ->from('stock_batches as sb')
                    ->whereColumn('sb.barang_id', 'barang.id')
                    ->where('sb.vendor_id', $vendorId);
            });
        }

        // Filter by batch date range (barang that had batch in range)
        if ($request->filled('tanggal_mulai') || $request->filled('tanggal_akhir')) {
            $mulai = $request->input('tanggal_mulai');
            $akhir = $request->input('tanggal_akhir');
            $query->whereExists(function($sub) use ($mulai, $akhir){
                $sub->select(DB::raw(1))
                    ->from('stock_batches as sb2')
                    ->whereColumn('sb2.barang_id', 'barang.id');
                if ($mulai) $sub->whereDate('sb2.received_at', '>=', $mulai);
                if ($akhir) $sub->whereDate('sb2.received_at', '<=', $akhir);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'nama');
        if ($sort === 'terbaru_masuk' || $sort === 'terlama_masuk') {
            // Join subquery for latest batch date
            $batchAgg = DB::table('stock_batches')
                ->select('barang_id', DB::raw('MAX(received_at) as last_in'))
                ->groupBy('barang_id');
            $query->leftJoinSub($batchAgg, 'ba', function($join){
                $join->on('ba.barang_id','=','barang.id');
            });
            $query->orderBy('ba.last_in', $sort === 'terbaru_masuk' ? 'desc' : 'asc')
                  ->orderBy('merk')->orderBy('jenis');
        } elseif ($sort === 'stok') {
            $query->orderBy('stok_barang', 'desc')->orderBy('merk')->orderBy('jenis');
        } elseif ($sort === 'harga') {
            $query->orderBy('harga_barang', 'desc')->orderBy('merk')->orderBy('jenis');
        } else {
            $query->orderBy('merk')->orderBy('jenis');
        }

        // Pagination for scalability
        $perPage = (int) ($request->input('per_page', 20));
        $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 20;
        $barangPaginated = $query->select('barang.*')->paginate($perPage)->withQueryString();

        // Group the current page items by kategori+merk
        $barangGrouped = $barangPaginated->getCollection()->groupBy(function($item){
            return $item->kategori . '|' . $item->merk;
        })->map(function($group){
            return [
                'kategori' => $group->first()->kategori,
                'merk' => $group->first()->merk,
                'kode_barang' => $group->first()->kode_barang,
                'deskripsi' => $group->first()->deskripsi,
                'total_stok' => $group->sum('stok_barang'),
                'varians' => $group,
                'first_item' => $group->first(),
            ];
        })->values();

        $vendors = Vendor::orderBy('nama')->get(['id','nama']);

        return view('barang.index', [
            'barangGrouped' => $barangGrouped,
            'barangPaginated' => $barangPaginated,
            'vendors' => $vendors,
        ]);
    }

    public function create()
    {
        return view('barang.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kategori' => 'required|string|size:2|regex:/^[A-Z]{2}$/',
            'kategori_nama' => 'nullable|string|max:50',
            'merk' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'varians' => 'required|array|min:1',
            'varians.*.jenis' => 'required|string|max:100',
            'varians.*.kode_jenis' => 'nullable|regex:/^\d{2}$/',
            'varians.*.kode_kemasan' => 'required|regex:/^\d{2}$/',
            'varians.*.ukuran_kemasan' => 'required|string|max:100',
            'varians.*.harga_barang' => 'required|integer|min:0',
            'varians.*.stok_barang' => 'required|integer|min:0',
            'varians.*.kode_barang_manual' => 'nullable|string|size:8',
            // Optional initial stock batch for all varians
            'init_stock' => 'nullable|boolean',
            'vendor_init_id' => 'nullable|exists:vendors,id',
            'vendor_init_baru' => 'nullable|string|max:100',
            'vendor_init_kode' => 'nullable|string|max:20',
            'vendor_init_alamat' => 'nullable|string|max:255',
            'vendor_init_no_kontak' => 'nullable|string|max:30',
            'vendor_init_nama_sales' => 'nullable|string|max:100',
            'unit_cost_init' => 'nullable|numeric|min:0',
            'harga_jual_baru_init' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function() use ($data, $request) {
            // Generate kode_merk for code generation (now strictly 2 digits),
            // based only on new-format (8-char) items for this kategori. If none, start at 01.
            $latestKode = Barang::where('kategori', $data['kategori'])
                ->whereRaw('LENGTH(kode_barang)=8')
                ->selectRaw('MAX(CAST(SUBSTRING(kode_barang,3,2) AS UNSIGNED)) as max_kode')
                ->value('max_kode');
            $nextMerkCode = str_pad((string)(($latestKode ?? 0) + 1), 2, '0', STR_PAD_LEFT);

            // Prepare vendor for initial stock
            $initStock = (bool)($data['init_stock'] ?? false);
            $vendorId = $data['vendor_init_id'] ?? null;
            if ($initStock && !$vendorId && !empty($data['vendor_init_baru'])) {
                $vendor = \App\Models\Vendor::create([
                    'kode' => $data['vendor_init_kode'] ?? null,
                    'nama' => $data['vendor_init_baru'],
                    'alamat' => $data['vendor_init_alamat'] ?? null,
                    'no_kontak' => $data['vendor_init_no_kontak'] ?? null,
                    'nama_sales' => $data['vendor_init_nama_sales'] ?? null,
                ]);
                $vendorId = $vendor->id;
            }

            foreach ($data['varians'] as $varian) {
                $kodeJenis = !empty($varian['kode_jenis']) ? $varian['kode_jenis'] : '01';

                // Generate kode_barang (new format: KK + MM(2) + JJ(2) + KKEM(2) => 8 chars)
                if (!empty($varian['kode_barang_manual'])) {
                    $kodeBarang = $varian['kode_barang_manual'];
                } else {
                    $kodeBarang = $data['kategori'] . $nextMerkCode . $kodeJenis . $varian['kode_kemasan'];
                }

                /** @var Barang $b */
                $b = Barang::create([
                    'kode_barang' => $kodeBarang,
                    'kategori' => $data['kategori'],
                    'merk' => $data['merk'],
                    'jenis' => $varian['jenis'],
                    'ukuran_kemasan' => $varian['ukuran_kemasan'],
                    'harga_barang' => $varian['harga_barang'],
                    'stok_barang' => $varian['stok_barang'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                ]);

                // Create initial batch if requested and stok > 0
                if ($initStock && $b->stok_barang > 0 && $vendorId && isset($data['unit_cost_init'])) {
                    $batch = \App\Models\StockBatch::create([
                        'barang_id' => $b->id,
                        'vendor_id' => $vendorId,
                        'qty_received' => $b->stok_barang,
                        'qty_remaining' => $b->stok_barang,
                        'unit_cost' => $data['unit_cost_init'],
                        'sell_price_at_receive' => $data['harga_jual_baru_init'] ?? $b->harga_barang,
                        'notes' => 'Initial stock saat buat barang',
                    ]);

                    // Movement log
                    \App\Models\StockMovement::create([
                        'barang_id' => $b->id,
                        'vendor_id' => $vendorId,
                        'type' => 'in',
                        'qty' => $b->stok_barang,
                        'before_stock' => 0,
                        'after_stock' => $b->stok_barang,
                        'unit_cost' => $data['unit_cost_init'],
                        'unit_price' => $b->harga_barang,
                        'stock_batch_id' => $batch->id,
                        'notes' => 'Initial stock saat buat barang',
                    ]);
                }
            }
        });

        return redirect()->route('barang.index')->with('success', 'Barang dengan ' . count($data['varians']) . ' varian berhasil ditambahkan');
    }

    public function edit(Barang $barang)
    {
        // Load all varians with same kategori & merk
        $varians = Barang::where('kategori', $barang->kategori)
            ->where('merk', $barang->merk)
            ->orderBy('jenis')
            ->orderBy('ukuran_kemasan')
            ->get();
        
        return view('barang.edit', compact('barang', 'varians'));
    }

    public function update(Request $request, Barang $barang)
    {
        $data = $request->validate([
            'kategori' => 'required|string|size:2|regex:/^[A-Z]{2}$/',
            'kategori_nama' => 'nullable|string|max:50',
            'merk' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'varians' => 'required|array|min:1',
            'varians.*.id' => 'nullable|exists:barang,id',
            'varians.*.jenis' => 'required|string|max:100',
            'varians.*.kode_jenis' => 'nullable|regex:/^\d{2}$/',
            'varians.*.kode_kemasan' => 'required|regex:/^\d{2}$/',
            'varians.*.ukuran_kemasan' => 'required|string|max:100',
            'varians.*.harga_barang' => 'required|integer|min:0',
            'varians.*.stok_barang' => 'required|integer|min:0',
            'varians.*.kode_barang_manual' => 'nullable|string|size:8',
        ]);

        $oldKategori = $barang->kategori;
        $oldMerk = $barang->merk;
        
        // Get existing varian IDs
        $existingIds = collect($data['varians'])->pluck('id')->filter()->toArray();
        
        // Delete varians that are no longer in the form (same kategori & merk)
        Barang::where('kategori', $oldKategori)
            ->where('merk', $oldMerk)
            ->whereNotIn('id', $existingIds)
            ->each(function($item) {
                // Check if varian has transactions
                $hasTransactions = TransaksiItem::where('barang_id', $item->id)->exists();
                if (!$hasTransactions) {
                    $item->delete();
                }
            });
        
    // Get merk code (legacy 3 digits -> trim to first 2; new codes already 2 digits)
    $merkCode = substr($barang->kode_barang, 2, 2);
        
        // Update or create varians
        foreach ($data['varians'] as $varianData) {
            if (!empty($varianData['id'])) {
                // Update existing varian
                $varianBarang = Barang::find($varianData['id']);
                if ($varianBarang) {
                    $kodeJenis = !empty($varianData['kode_jenis']) ? $varianData['kode_jenis'] : '01';
                    
                    // Generate or use manual kode_barang (8 chars)
                    if (!empty($varianData['kode_barang_manual'])) {
                        $kodeBarang = $varianData['kode_barang_manual'];
                    } else {
                        $kodeBarang = $data['kategori'] . $merkCode . $kodeJenis . $varianData['kode_kemasan'];
                    }
                    
                    $varianBarang->update([
                        'kode_barang' => $kodeBarang,
                        'kategori' => $data['kategori'],
                        'merk' => $data['merk'],
                        'jenis' => $varianData['jenis'],
                        'ukuran_kemasan' => $varianData['ukuran_kemasan'],
                        'harga_barang' => $varianData['harga_barang'],
                        'stok_barang' => $varianData['stok_barang'],
                        'deskripsi' => $data['deskripsi'] ?? null,
                    ]);
                }
            } else {
                // Create new varian with same merk code
                $kodeJenis = !empty($varianData['kode_jenis']) ? $varianData['kode_jenis'] : '01';
                
                if (!empty($varianData['kode_barang_manual'])) {
                    $kodeBarang = $varianData['kode_barang_manual'];
                } else {
                    $kodeBarang = $data['kategori'] . $merkCode . $kodeJenis . $varianData['kode_kemasan'];
                }
                
                Barang::create([
                    'kode_barang' => $kodeBarang,
                    'kategori' => $data['kategori'],
                    'merk' => $data['merk'],
                    'jenis' => $varianData['jenis'],
                    'ukuran_kemasan' => $varianData['ukuran_kemasan'],
                    'harga_barang' => $varianData['harga_barang'],
                    'stok_barang' => $varianData['stok_barang'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                ]);
            }
        }

        return redirect()->route('barang.index')->with('success', 'Barang dan varian berhasil diperbarui');
    }

    public function destroy(Barang $barang)
    {
        // Check if this item has transactions
        $hasTransactions = TransaksiItem::where('barang_id', $barang->id)->exists();
        
        if ($hasTransactions) {
            return redirect()
                ->route('barang.index')
                ->with('error', 'Tidak dapat menghapus barang karena sudah digunakan dalam transaksi.');
        }

        // Delete this single barang item
        $barang->delete();
        
        return redirect()->route('barang.index')->with('success', 'Varian barang berhasil dihapus');
    }
}
