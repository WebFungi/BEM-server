<?php

namespace App\Console\Commands;

use App\Models\Fungi;
use App\Models\Occurrence;
use App\Services\Platforms\IUCN\IUCN;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use App\Utils\CustomSheetReader;
use App\Utils\Enums\BemClassification;
use App\Utils\Enums\RedListClassification;
use App\Utils\Enums\OccurrenceTypes;
use App\Utils\Enums\StatesAcronyms;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Mockery\Matcher\Not;
use Spatie\FlareClient\Http\Exceptions\NotFound;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RegisterFungiOccurences extends Command
{
    protected $signature = 'app:register-fungi-occurrences';
    protected $description = 'Importa fungos e ocorrências com base no arquivo Brazilian_Edible_Mushrooms_Final.xlsx';

    public function __construct(private IUCN $iucn)
    {
        parent::__construct($iucn);
    }

    public function handle(IUCN $iucn): void
    {
        $this->info('Iniciando leitura da planilha.');
        $filePath = dirname(__FILE__, 4) . '/support/Brazilian_Edible_Mushrooms_Final.xlsx';

        [$fungis, $occurrences, $totalRows] = $this->loadSpreadsheet($filePath);

        $this->info('Iniciando importação para o banco de dados...');
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();



        $fungis->each(function ($row) use ($occurrences, $iucn, $bar) {
            $this->importFungi($row, $occurrences, $iucn, $bar);
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('Importação finalizada.');
    }

    private function loadSpreadsheet(string $filePath): array
    {
        $reader = new Xlsx();
        $sheetInfo = $reader->listWorksheetInfo($filePath)[0];

        $reader->setReadFilter(new CustomSheetReader(2, $sheetInfo['totalRows'], range('A', $sheetInfo['lastColumnLetter'])));
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['BEM', 'Literature_Ocurrence']);
        $spreadsheet = $reader->load($filePath);

        return [
            collect($spreadsheet->getSheetByName('BEM')->toArray())->splice(1),
            collect($spreadsheet->getSheetByName('Literature_Ocurrence')->toArray()),
            $sheetInfo['totalRows']
        ];
    }

    private function importFungi(array $fungiRow, Collection $occurrences, IUCN $iucn, $bar): void
    {
        $fungiLineId = $fungiRow[0];
        $inaturalistTaxa = $fungiRow[11];

        if (Fungi::where('inaturalist_taxa', $inaturalistTaxa)->exists()) {
            return;
        }

        try {
            DB::beginTransaction();

            $fungi = Fungi::updateOrCreate(
                ['inaturalist_taxa' => $inaturalistTaxa],
                $this->buildFungiData($fungiRow)
            );

            $fungiOccurrences = $this->buildOccurrences($fungiRow, $occurrences, $fungiLineId);
            $fungi->occurrences()->syncWithoutDetaching($fungiOccurrences);

            DB::commit();
            $bar->advance();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nErro ao importar espécie ID {$fungiLineId}: " . $e->getMessage());
        }
    }

    private function fetchIUCNData(string $genus, string $species): int
    {
        try {
            $data = $this->iucn->getByScientificName($genus, $species);
            $latestAssessment = collect($data['assessments'])->first(fn($assessment) => $assessment['latest']);
            return RedListClassification::getValueByName($latestAssessment['red_list_category_code'])
                ?? RedListClassification::NE->value;
        } catch (Throwable $th) {
            return RedListClassification::NA->value;
        }
    }

    private function buildFungiData(array $row): array
    {
        return [
            'uuid' => Str::uuid(),
            'kingdom' => trim($row[1]),
            'phylum' => trim($row[2]),
            'class' => trim($row[3]),
            'order' => trim($row[4]),
            'family' => trim($row[5]),
            'genus' => trim($row[6]),
            'specie' => trim($row[7]),
            'scientific_name' => trim($row[6]) . ' ' . trim($row[7]),
            'authors' => trim($row[8]),
            'brazilian_type' => $row[9],
            'brazilian_type_synonym' => $row[10],
            'inaturalist_taxa' => $row[11],
            'popular_name' => trim($row[12]),
            'bem' => BemClassification::getValueByName(trim($row[13])),
            'threatened' => $this->fetchIUCNData(trim($row[6]), trim($row[7])),
            'description' => null,
        ];
    }

    private function buildOccurrences(array $fungiRow, Collection $allOccurrences, $fungiLineId): array
    {
        $relatedOccurrences = $allOccurrences->filter(fn($row) => $row[0] == $fungiLineId);
        $occurrenceIds = [];

        foreach ($relatedOccurrences as $occurrence) {
            $states = explode(',', $occurrence[1]);

            foreach ($states as $acronym) {
                $acr = strtoupper(trim($acronym));
                if (Str::length($acr) === 2) {
                    $geo = StatesAcronyms::getGeolocalizationByAcronyms($acr);
                    $occurrenceModel = Occurrence::create([
                        'uuid' => Str::uuid(),
                        'inaturalist_taxa' => null,
                        'specieslink_id' => null,
                        'type' => $fungiRow[8] ? OccurrenceTypes::Literature->value : null,
                        'state_acronym' => $acr,
                        'state_name' => StatesAcronyms::getStateByAcronym($acr),
                        'habitat' => trim($occurrence[2]),
                        'literature_reference' => null,
                        'latitude' => $geo['latitude'],
                        'longitude' => $geo['longitude'],
                        'curation' => true,
                    ]);
                    $occurrenceIds[] = $occurrenceModel->id;
                }
            }
        }

        return $occurrenceIds;
    }
}
