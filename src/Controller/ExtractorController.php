<?php

namespace Drupal\extractor\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\Core\Database\Database;
/**
 * Controller for handling file upload and processing.
 */
class ExtractorController extends ControllerBase {

  public function content() {
    return [
      '#theme' => 'extractor_page',
    ];
  }
  /**
   * Handles file upload and returns JSON response with data from uploaded XLS file.
   */
  public function upload(Request $request) {
    // Проверяем, что файл был загружен
    if (!$request->files->has('file')) {
      return new JsonResponse(['message' => 'No file uploaded'], 400);
    }

    $file = $request->files->get('file');

    // Проверяем ошибки загрузки файла
    if ($file->getError() !== UPLOAD_ERR_OK) {
      return new JsonResponse(['message' => 'File upload error'], 400);
    }

    // Получаем временный путь загруженного файла
    $tempPath = $file->getRealPath();

    // Обработка загруженного файла с использованием PhpSpreadsheet
    try {
      $spreadsheet = IOFactory::load($tempPath);

      // Получаем третий лист (нумерация начинается с 0)
      $sheet = $spreadsheet->getSheet(2);

      // Читаем данные из листа, пропуская первую строку (индексация строк начинается с 1)
      $data = [];
      foreach ($sheet->getRowIterator(2) as $row) { // RowIterator(2) начинает с третьей строки
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $rowData = [];
        foreach ($cellIterator as $cell) {
          $rowData[] = $cell->getValue();
        }
        $data[] = $rowData;
      }
      // Запись данных в базу данных Drupal
      $this->saveDataToDatabase($data);
      // Возвращаем JSON ответ с данными
      return new JsonResponse($data);
    } catch (\Exception $e) {
      // В случае ошибки возвращаем JSON ответ с сообщением об ошибке
      return new JsonResponse(['message' => 'Error processing file'], 500);
    }
  }

  // функция сохранения данных в бд. (не прописана в routing.yml для исключения sql-иньекций)

  private function saveDataToDatabase($data) {

    // Подключение к базе данных Drupal
    $connection = Database::getConnection();

    // Итерируемся по данным и вставляем каждую запись в таблицу employees
    foreach ($data as $employee) {
      $connection->insert('employees')
        ->fields([
          'name' => $employee[0],
          'sec_name' => $employee[1],
          'thrd_name' => $employee[2],
          'age' => $employee[3],
          'position' => $employee[4],
        ])
        ->execute();
    }
  }

}
