<?php

namespace App\Jobs;

use App\Models\Taxonomy;
use App\Services\Platforms\MycoBank\MycoBank;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateTaxonomyFromMycoBank implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MycoBank $service): void
    {
        $filepath = $service->downloadFungiList();

        $reader = ReaderEntityFactory::createXLSXReader();

        try {
            $reader->open($filepath);
            $localTaxa = $this->getLocalTaxonomies();

            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;

                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $cells = $row->toArray();

                    // Map header columns on first row
                    if ($rowIndex === 1) {
                        $header = array_flip($cells);
                        continue;
                    }

                    if (!$header || !isset($header['Taxon name'])) {
                        Log::warning('Invalid sheet format: missing "Taxon name" column.');
                        break;
                    }

                    $taxonName = trim($cells[$header['Taxon name']] ?? '');
                    if ($taxonName === '') {
                        continue;
                    }

                    $fungi = $localTaxa[$taxonName] ?? null;
                    if (!$fungi) {
                        continue;
                    }

                    $this->updateFungiRecord($fungi, $cells, $header);
                }
            }
        } catch (\Throwable $e) {
            Log::error('MycoBank import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        } finally {
            $reader->close();
        }
    }
    private function getLocalTaxonomies(): array
    {
        return Taxonomy::all()
            ->mapWithKeys(fn($t) => [$t->genus . ' ' . $t->specie => $t])
            ->toArray();
    }

    private function updateFungiRecord(array $fungiData, array $cells, array $header): void
    {
        $fungi = Taxonomy::find($fungiData['id']);
        $mycoBankId = $cells[$header['MycoBank #']] ?? null;

        $updates = [];

        if (!$fungi) {
            Log::warning("Taxonomy not found for ID: {$fungiData['id']}");
            return;
        }

        if ($mycoBankId && $mycoBankId !== $fungi->external_id) {
            $updates['external_id'] = $mycoBankId;
        }

        if (!empty($updates)) {
            $fungi->fill($updates)->save();
        }
    }
}
