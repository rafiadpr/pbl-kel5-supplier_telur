<x-filament-panels::page>
    {{-- FORM FILTER --}}
    <form wire:submit="submitFilter">
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
             <x-filament::button type="submit">
                Tampilkan Laporan
            </x-filament::button>
        </div>
    </form>

    <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

    {{-- HASIL LAPORAN --}}
    @php
        $data = $reportData;
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- CARD 1: OMZET --}}
        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Penjualan</div>
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                Rp {{ number_format($data['sales'], 0, ',', '.') }}
            </div>
        </x-filament::section>

        {{-- CARD 2: HPP --}}
        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Modal Barang (HPP)</div>
            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">
                (Rp {{ number_format($data['cogs'], 0, ',', '.') }})
            </div>
        </x-filament::section>

        {{-- CARD 3: OPERASIONAL --}}
        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Biaya Operasional</div>
            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">Gaji, Bensin, dll</div>
            <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                (Rp {{ number_format($data['expenses'], 0, ',', '.') }})
            </div>
        </x-filament::section>

        {{-- CARD 4: LABA BERSIH --}}
        <x-filament::section class="{{ $data['netProfit'] >= 0 ? 'border-l-4 border-success-500' : 'border-l-4 border-danger-500' }}">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Keuntungan Bersih</div>
            <div class="text-3xl font-bold {{ $data['netProfit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                Rp {{ number_format($data['netProfit'], 0, ',', '.') }}
            </div>
        </x-filament::section>
    </div>

    {{-- SECTION ARUS KAS: BELANJA STOK --}}
    <div class="border-t border-dashed border-gray-300 dark:border-gray-600 my-6"></div>
    <div class="mb-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            📦 Informasi Arus Kas (Cashflow)
        </span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <x-filament::section class="border-l-4 border-warning-500">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Belanja Stok (Modal Keluar)</div>
            <div class="text-xs text-gray-400 dark:text-gray-500 mb-1">
                Total uang yang dibayarkan ke supplier (termasuk telur yang belum laku)
            </div>
            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                Rp {{ number_format($data['totalCapitalSpent'], 0, ',', '.') }}
            </div>
        </x-filament::section>
    </div>

    {{-- TABEL RINCIAN SEDERHANA --}}
    <x-filament::section>
        <h3 class="font-bold text-lg mb-4 dark:text-white">Ringkasan Kinerja</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Periode: {{ \Carbon\Carbon::parse($data['startDate'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($data['endDate'])->format('d/m/Y') }}
        </p>
        <table class="w-full text-sm text-left">
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr>
                    <td class="py-2 dark:text-gray-300">Pendapatan Kotor (Omzet)</td>
                    <td class="py-2 text-right dark:text-gray-300">Rp {{ number_format($data['sales'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="py-2 text-gray-500 dark:text-gray-400">- Dikurangi Modal Stok (HPP)</td>
                    <td class="py-2 text-right text-gray-500 dark:text-gray-400">(Rp {{ number_format($data['cogs'], 0, ',', '.') }})</td>
                </tr>
                <tr class="font-bold bg-gray-50 dark:bg-gray-800">
                    <td class="py-2 pl-2 dark:text-white">LABA KOTOR (Margin Dagang)</td>
                    <td class="py-2 text-right pr-2 dark:text-white">Rp {{ number_format($data['grossProfit'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="py-2 text-red-500 dark:text-red-400">- Dikurangi Biaya Operasional</td>
                    <td class="py-2 text-right text-red-500 dark:text-red-400">(Rp {{ number_format($data['expenses'], 0, ',', '.') }})</td>
                </tr>
                <tr class="font-bold text-lg border-t-2 border-gray-300 dark:border-gray-600">
                    <td class="py-4 dark:text-white">LABA BERSIH</td>
                    <td class="py-4 text-right {{ $data['netProfit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        Rp {{ number_format($data['netProfit'], 0, ',', '.') }}
                    </td>
                </tr>
                {{-- Separator --}}
                <tr>
                    <td colspan="2" class="py-2">
                        <div class="border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                    </td>
                </tr>
                {{-- Belanja Stok (Arus Kas) --}}
                <tr class="bg-warning-50 dark:bg-warning-950/20">
                    <td class="py-2 pl-2 text-warning-700 dark:text-warning-400 font-medium">
                        📦 Total Belanja Stok (Modal Keluar)
                        <div class="text-xs font-normal text-gray-400 dark:text-gray-500">Arus Kas — bukan pengurang laba</div>
                    </td>
                    <td class="py-2 text-right pr-2 text-warning-700 dark:text-warning-400 font-bold">
                        Rp {{ number_format($data['totalCapitalSpent'], 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </x-filament::section>

</x-filament-panels::page>
