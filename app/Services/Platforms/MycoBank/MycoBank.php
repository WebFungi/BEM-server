<?php

declare(strict_types=1);

namespace App\Services\Platforms\MycoBank;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Shuchkin\SimpleXLSX;
use ZipArchive;

class MycoBank
{
    public function __construct(protected ZipArchive $zipArchive) {}

    public function downloadFungiList(): string
    {
        $name = storage_path('app/MycoBank/fungi_list_' . date('Y_m_d_H_i') . '.zip');

        $client = new Client();
        $client->get('https://www.mycobank.org/Images/MBList.zip', [
            'sink' => $name
        ]);

        return $this->extractXlsxFromZip($name);
    }

    public function extractXlsxFromZip($zipPath)
    {
        $destinationPath = str_replace('.zip', '', $zipPath);
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) === true) {
            $tempPath = storage_path('app/temp_unzip_' . uniqid());
            mkdir($tempPath, 7777, true);

            $zip->extractTo($tempPath);
            $zip->close();

            $xlsxFile = collect(scandir($tempPath))
                ->first(fn($file) => str_ends_with($file, '.xlsx'));

            if ($xlsxFile) {
                $xlsxSource = $tempPath . '/' . $xlsxFile;
                $xlsxTarget = $destinationPath . '/' . $xlsxFile;

                rename($xlsxSource, $xlsxTarget);
                $this->deleteDirectory($tempPath);

                return $xlsxTarget;
            } else {
                $this->deleteDirectory($tempPath);
                throw new \Exception("Nenhum arquivo XLSX encontrado no zip.");
            }
        } else {
            throw new \Exception("Não foi possível abrir o arquivo ZIP.");
        }
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            $this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item);
        }
        return rmdir($dir);
    }
}
