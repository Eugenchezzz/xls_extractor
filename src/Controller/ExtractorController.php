<?php

namespace Drupal\extractor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\Core\Database\Database;

class ExtractorController extends ControllerBase {

  // функция рендера страницы extractor-page.html.twig
  public function content() {
    return [
      '#theme' => 'extractor_page',
    ];
  }

  // функция обработки нажатия на стороне сервера
  public function upload(Request $request) {

    // проверка на загрузку файла
    if (!$request->files->has('file')) {
      return new JsonResponse(['message' => 'Файл не загружен!'], 400);
    }

    $file = $request->files->get('file');

    // проверка ошибок загрузки файла
    if ($file->getError() !== UPLOAD_ERR_OK) {
      return new JsonResponse(['message' => 'Ошибка загрузки файла'], 400);
    }

    // получение пути (временного) загружаемого файла
    $tempPath = $file->getRealPath();

    // обработка загруженного файла с использованием PhpSpreadsheet
    try {
      $spreadsheet = IOFactory::load($tempPath);

      // получение третьего листа (нумерация начинается с 0)
      $sheet = $spreadsheet->getSheet(2);

      // создание подключения к бд единожды
      $connection = Database::getConnection();
      // Читаем данные из листа, пропуская первую строку (индексация строк начинается с 1)
      foreach ($sheet->getRowIterator(2) as $row) { // RowIterator(2) начинает со второй строки
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $rowData = [];
        foreach ($cellIterator as $cell) {
          $rowData[] = $cell->getValue();
        }
        // вставка данных в базу данных сразу после их извлечения
        $this->saveRowToDatabase($rowData, $connection);
      }
      // явное закрытие соединения
      $connection = null;

      // возврат JSON ответа с данными
      return new JsonResponse(['message' => 'Данные успешно загружены в базу данных!']);
    } catch (\Exception $e) {
      // в случае ошибки возврат JSON ответа с сообщением об ошибке
      return new JsonResponse(['message' => 'Ошибка обработки файла'], 500);
    }
  }

  // функция сохранения данных в бд. private - так как вызывается исключительно из других функций класса ExtractorController
  private function saveRowToDatabase($rowData, $connection) {
    // поочередное добавление каждого кортежа данных в бд

    $connection->insert('employees') // при необходимости изменить тут название базы данных
      ->fields([
        'first_name' => $rowData[0],
        'last_name' => $rowData[1],
        'middle_name' => $rowData[2],
        'age' => $rowData[3],
        'position' => $rowData[4],
      ])
      ->execute();
  }
}
