<?php

namespace Tests\Feature;

use App\Models\CourseCatalog;
use App\Models\ExamFormTemplate;
use App\Models\ExamRoutine;
use App\Models\ExamScheduleObservation;
use App\Models\ExamSheet;
use App\Models\ExamSlotTimeSeriesMetric;
use Database\Seeders\AcademicRoutineSeeder;
use Database\Seeders\CourseOfferingSeeder;
use Database\Seeders\ExamFormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExamRoutineAndCoverPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseOfferingSeeder::class);
        $this->seed(AcademicRoutineSeeder::class);
    }

    public function test_exam_routine_tab_renders_distinct_midterm_and_final_views(): void
    {
        $midterm = $this->get('/?tab=exam_routine&exam_type=Midterm');
        $midterm->assertStatus(200);
        $midterm->assertViewHas('activeTab', 'exam_routine');
        $midterm->assertViewHas('examType', 'Midterm');
        $midterm->assertSee('Exam Routine &amp; Sheet Browser', false);
        $midterm->assertSee('Browse Midterm Exam Sheet');

        $final = $this->get('/?exam_type=Final');
        $final->assertStatus(200);
        $final->assertViewHas('activeTab', 'exam_routine');
        $final->assertViewHas('examType', 'Final');
        $final->assertSee('Browse Final Exam Sheet');
    }

    public function test_upload_csv_exam_sheet_isolates_midterm_and_final_and_renders_raw_sheet_browser(): void
    {
        Storage::fake('local');

        $csv = implode("\n", [
            'Exam Date,Day,Time Slot,Batch,Section,Track,Course Code,Course Title,Room No,Teacher Initials,Notes',
            '2026-10-25,Sunday,09:00 AM - 11:00 AM,45,A,,SE215,Algorithms Design & Analysis,604,DSM,Bring calculator',
            '2026-10-26,Monday,11:30 AM - 01:30 PM,44,B,,SE311,Database Management System,802,MH,Closed book',
        ]);

        $file = UploadedFile::fake()->createWithContent('midterm_fall_2026.csv', $csv);

        $response = $this->post('/exam-routine/upload', [
            'exam_type' => 'Midterm',
            'exam_sheet' => $file,
        ]);

        $response->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Midterm',
        ]));

        $this->assertDatabaseHas('exam_sheets', [
            'exam_type' => 'Midterm',
            'original_filename' => 'midterm_fall_2026.csv',
            'row_count' => 2,
        ]);
        $this->assertDatabaseHas('exam_routines', [
            'exam_type' => 'Midterm',
            'course_code' => 'SE215',
            'batch' => 45,
            'section' => 'A',
            'room_no' => '604',
            'building' => 'AB3',
        ]);
        $this->assertDatabaseHas('exam_routines', [
            'exam_type' => 'Midterm',
            'course_code' => 'SE311',
            'room_no' => '802',
            'building' => 'AB4',
        ]);
        $this->assertSame(0, ExamRoutine::forExamType('Final')->count());

        $sheetView = $this->get('/?tab=exam_routine&exam_type=Midterm&exam_view_mode=sheet');
        $sheetView->assertStatus(200);
        $sheetView->assertSee('Midterm Raw Exam Sheet Browser');
        $sheetView->assertSee('midterm_fall_2026.csv');
        $sheetView->assertSee('SE215');
        $sheetView->assertSee('604');

        $finalSchedule = $this->get('/?tab=exam_routine&exam_type=Final');
        $finalSchedule->assertStatus(200);
        $finalSchedule->assertSee('No Final Exam Routine Loaded');
    }

    public function test_upload_multisheet_xlsx_auto_selects_exam_type_sheet_and_allows_switching(): void
    {
        Storage::fake('local');

        $xlsxBinary = $this->buildMultiSheetXlsx([
            'Midterm Schedule' => [
                ['Exam Date', 'Time Slot', 'Batch', 'Section', 'Course Code', 'Course Title', 'Room No', 'Teacher'],
                ['2026-10-25', '09:00 AM - 11:00 AM', '45', 'A', 'SE215', 'Algorithms Lab Exam', '604', 'DSM'],
            ],
            'Final Schedule' => [
                ['Exam Date', 'Time Slot', 'Batch', 'Section', 'Course Code', 'Course Title', 'Room No', 'Teacher'],
                ['2026-12-14', '02:00 PM - 04:00 PM', '42', 'B', 'SE321', 'Software Engineering Final', '1402', 'AR'],
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('diu_swe_exams.xlsx', $xlsxBinary);

        $upload = $this->post('/exam-routine/upload', [
            'exam_type' => 'Final',
            'exam_sheet' => $file,
        ]);

        $upload->assertSessionHasNoErrors();
        $upload->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Final',
        ]));

        /** @var ExamSheet $sheet */
        $sheet = ExamSheet::where('exam_type', 'Final')->firstOrFail();
        $this->assertSame('Final Schedule', $sheet->active_sheet_name);
        $this->assertSame(['Midterm Schedule', 'Final Schedule'], $sheet->available_sheets);
        $this->assertDatabaseHas('exam_routines', [
            'exam_type' => 'Final',
            'course_code' => 'SE321',
            'batch' => 42,
            'building' => 'Main',
        ]);

        $switch = $this->post('/exam-routine/select-sheet', [
            'exam_type' => 'Final',
            'sheet_name' => 'Midterm Schedule',
        ]);

        $switch->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Final',
        ]));

        $sheet->refresh();
        $this->assertSame('Midterm Schedule', $sheet->active_sheet_name);
        $this->assertDatabaseHas('exam_routines', [
            'exam_type' => 'Final',
            'course_code' => 'SE215',
            'batch' => 45,
        ]);
        $this->assertDatabaseMissing('exam_routines', [
            'exam_type' => 'Final',
            'course_code' => 'SE321',
        ]);
    }

    public function test_manual_slot_filtering_exports_api_and_clear_by_exam_type(): void
    {
        ExamRoutine::create([
            'semester' => 'Fall',
            'year' => '2026',
            'exam_type' => 'Midterm',
            'exam_date' => '2026-10-24',
            'day_of_week' => 'Saturday',
            'time_slot' => '09:00 AM - 11:00 AM',
            'batch' => 46,
            'section' => 'A',
            'course_code' => 'SE121',
            'course_name' => 'Structured Programming',
            'room_no' => 'KT-205',
            'building' => 'KT',
        ]);

        $slotResp = $this->post('/exam-routine/slot', [
            'exam_type' => 'Final',
            'exam_date' => '2026-12-15',
            'time_slot' => '11:30 AM - 01:30 PM',
            'batch' => 45,
            'section' => 'C',
            'course_code' => 'se311',
            'room_no' => '804',
            'teacher_initials' => 'dsm',
        ]);

        $slotResp->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Final',
        ]));

        $this->assertDatabaseHas('exam_routines', [
            'exam_type' => 'Final',
            'course_code' => 'SE311',
            'day_of_week' => 'Tuesday',
            'building' => 'AB4',
            'teacher_initials' => 'DSM',
        ]);

        $filtered = $this->get('/?tab=exam_routine&exam_type=Final&exam_batch=45&exam_search=SE311');
        $filtered->assertStatus(200);
        $filtered->assertSee('SE311');
        $filtered->assertSee('804');

        $csvExport = $this->get('/exam-routine/export/csv?exam_type=Final&exam_batch=45');
        $csvExport->assertStatus(200);
        $csvExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('SE311', $csvExport->getContent());

        $icsExport = $this->get('/exam-routine/export/ics?exam_type=Final');
        $icsExport->assertStatus(200);
        $icsExport->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VEVENT', $icsExport->getContent());
        $this->assertStringContainsString('[Final] SE311', $icsExport->getContent());

        $template = $this->get('/exam-routine/template?exam_type=Midterm');
        $template->assertStatus(200);
        $this->assertStringContainsString('Exam Date', $template->getContent());

        $api = $this->getJson('/api/v1/exam-routine?exam_type=Final&batch=45');
        $api->assertStatus(200);
        $api->assertJson([
            'status' => 'success',
            'feature' => 'exam_routine',
            'meta' => [
                'exam_type' => 'Final',
                'batch' => 45,
                'total_entries' => 1,
            ],
        ]);

        $clear = $this->delete('/exam-routine/clear', ['exam_type' => 'Final']);
        $clear->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Final',
        ]));

        $this->assertSame(0, ExamRoutine::forExamType('Final')->count());
        $this->assertSame(1, ExamRoutine::forExamType('Midterm')->count());
    }

    public function test_exam_sheet_upload_validates_exam_type_and_corrupt_xlsx(): void
    {
        Storage::fake('local');

        $badType = $this->post('/exam-routine/upload', [
            'exam_type' => 'Quiz',
            'exam_sheet' => UploadedFile::fake()->create('quiz.csv', 5, 'text/csv'),
        ]);
        $badType->assertSessionHasErrors('exam_type');

        $corruptXlsx = UploadedFile::fake()->createWithContent('broken.xlsx', 'not-a-valid-zip-file');
        $badFile = $this->postJson('/exam-routine/upload', [
            'exam_type' => 'Midterm',
            'exam_sheet' => $corruptXlsx,
        ]);
        $badFile->assertStatus(422);
        $badFile->assertJson(['status' => 'error']);
    }

    public function test_cover_page_generator_tab_renders_all_four_cover_types_and_presets(): void
    {
        $response = $this->get('/?tab=cover_page');

        $response->assertStatus(200);
        $response->assertViewHas('activeTab', 'cover_page');
        $response->assertSee('Assignment &amp; Report Cover Page Generator', false);
        $response->assertSee('Lab Report');
        $response->assertSee('Final Lab Report');
        $response->assertSee('Theory Assessment Report');
        $response->assertSee('Assignment');
        $response->assertSee('coverPageSheet');
        $response->assertSee('Quick-Fill DIU SWE Course');
        $response->assertSee('Quick-Fill SWE Faculty Member');
        $response->assertSee('Download Word (.doc)');
        $response->assertSee('exportCoverPageDoc');
        $response->assertSee('Daffodil Smart City, Birulia, Dhaka');
        $response->assertDontSee('Ashulia');

        $deepLink = $this->get('/?cover_type=theory_assessment');
        $deepLink->assertStatus(200);
        $deepLink->assertViewHas('activeTab', 'cover_page');
        $deepLink->assertViewHas('coverType', 'theory_assessment');
    }

    public function test_exam_form_templates_memorize_forms_only_with_zero_past_schedule_rows(): void
    {
        $this->seed(ExamFormTemplateSeeder::class);

        $this->assertSame(9, ExamFormTemplate::query()->count());
        $this->assertDatabaseHas('exam_form_templates', [
            'exam_type' => 'Midterm',
            'slot_code' => 'Slot A',
            'duration_minutes' => 90,
        ]);
        $this->assertDatabaseHas('exam_form_templates', [
            'exam_type' => 'Final',
            'slot_code' => 'Slot B',
            'time_label' => '12:00 PM - 02:00 PM',
            'duration_minutes' => 120,
        ]);
        $this->assertDatabaseHas('exam_form_templates', [
            'exam_type' => 'Overlap',
            'slot_code' => 'Slot A',
            'time_label' => '08:30 AM - 10:00 AM',
            'duration_minutes' => 90,
        ]);

        // Past routines are NEVER pre-seeded as active schedules
        $this->assertSame(0, ExamRoutine::query()->count());
        $this->assertSame(0, ExamScheduleObservation::query()->count());
        $this->assertSame(0, ExamSlotTimeSeriesMetric::query()->count());
    }

    public function test_uploading_department_matrix_grid_immediately_updates_complexity_and_time_series_tables(): void
    {
        Storage::fake('local');

        $csvStream = fopen('php://temp', 'r+');
        $rows = [
            ['Daffodil International University', '', '', '', '', '', ''],
            ['Department of Software Engineering', '', '', '', '', '', ''],
            ['Final Examination Routine, Spring 2027', '', '', '', '', '', ''],
            ['Date', 'Slot A: 9:00 am - 11:00 am', 'Batch', 'Slot B: 12:00 am - 02:00 pm', 'Batch', 'Slot C: 03:00 pm - 05:00 pm', 'Batch'],
            ["19/04/2027\nMonday", "SE 213 / SWE 223\nDigital Electronics & Logic Design\n773", '46', "ENG 101\nEnglish I\n696", '48', "DS 331\nIntroduction To Data Science\n340", '41-DS Major'],
            ['', "GE 324\nBusiness Analysis & Communication\n5", "Self Study\nMAB", "SE 321\nSoftware Engineering Web Application\n16", 'N/A', "ST 411\nAgile Testing\n40", '41-ST Major'],
            ['Slot', 'Slot A: 8:30 am - 10:00 am', 'Batch', 'Slot B: 11:30 am - 1:00 pm', 'Batch', 'Slot C: 2:30 am - 4:00 pm', 'Batch'],
            ["24/04/2027\nSaturday", 'Overlap', '', 'Overlap', '', 'Overlap', ''],
            ['Instructions for Students', '', '', '', '', '', ''],
        ];
        foreach ($rows as $r) {
            fputcsv($csvStream, $r, escape: '\\');
        }
        rewind($csvStream);
        $csvContent = (string) stream_get_contents($csvStream);
        fclose($csvStream);

        $file = UploadedFile::fake()->createWithContent('spring_2027_final_matrix.csv', $csvContent);

        $upload = $this->post('/exam-routine/upload', [
            'exam_type' => 'Final',
            'exam_sheet' => $file,
        ]);

        $upload->assertSessionHasNoErrors();
        $upload->assertRedirect(route('routine.index', [
            'tab' => 'exam_routine',
            'exam_type' => 'Final',
        ]));

        $this->assertDatabaseHas('academic_terms', [
            'term_code' => '2027-1-Spring',
            'semester_name' => 'Spring',
            'academic_year' => 2027,
            'is_active' => true,
        ]);

        /** @var CourseCatalog $se213 */
        $se213 = CourseCatalog::query()->where('course_code', 'SE213')->firstOrFail();
        $this->assertSame(['SWE223'], $se213->equivalent_codes);
        $this->assertSame(200, $se213->course_level);

        $this->assertDatabaseHas('exam_schedule_observations', [
            'exam_type' => 'Final',
            'course_code' => 'SE213',
            'equivalent_course_code' => 'SWE223',
            'student_count' => 773,
            'rooms_estimated' => 23,
            'duration_minutes' => 120,
            'cohort_scope' => 'Regular',
        ]);
        $this->assertDatabaseHas('exam_schedule_observations', [
            'exam_type' => 'Final',
            'course_code' => 'GE324',
            'cohort_scope' => 'Self Study',
            'is_self_study' => true,
            'teacher_initials' => 'MAB',
        ]);
        $this->assertDatabaseHas('exam_schedule_observations', [
            'exam_type' => 'Final',
            'course_code' => 'OVERLAP',
            'cohort_scope' => 'Overlap',
            'is_overlap' => true,
        ]);

        $this->assertDatabaseHas('exam_slot_time_series_metrics', [
            'exam_type' => 'Final',
            'slot_code' => 'Slot A',
            'concurrent_courses_count' => 2,
            'total_students' => 778,
            'cross_listed_courses_count' => 1,
        ]);

        $analytics = $this->getJson('/api/v1/exam-analytics?exam_type=Final');
        $analytics->assertStatus(200);
        $analytics->assertJson([
            'status' => 'success',
            'feature' => 'exam_complexity_and_time_series_analytics',
        ]);
        $this->assertGreaterThan(0, count($analytics->json('payload.time_series')));
        $this->assertArrayHasKey('Final', $analytics->json('payload.memorized_forms'));

        // Clearing immediately syncs the observation & metric tables
        $this->delete('/exam-routine/clear', ['exam_type' => 'Final']);
        $this->assertSame(0, ExamScheduleObservation::query()->where('exam_type', 'Final')->count());
        $this->assertSame(0, ExamSlotTimeSeriesMetric::query()->where('exam_type', 'Final')->count());
    }

    /**
     * Build a minimal valid multi-sheet .xlsx workbook binary string in memory.
     *
     * @param  array<string, array<int, array<int, string>>>  $sheets
     */
    private function buildMultiSheetXlsx(array $sheets): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $contentTypesOverrides = '';
        $workbookSheetsXml = '';
        $workbookRelsXml = '';
        $sheetIdx = 1;

        foreach ($sheets as $sheetName => $rows) {
            $safeName = htmlspecialchars($sheetName, ENT_XML1);
            $contentTypesOverrides .= "<Override PartName=\"/xl/worksheets/sheet{$sheetIdx}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $workbookSheetsXml .= "<sheet name=\"{$safeName}\" sheetId=\"{$sheetIdx}\" r:id=\"rId{$sheetIdx}\"/>";
            $workbookRelsXml .= "<Relationship Id=\"rId{$sheetIdx}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$sheetIdx}.xml\"/>";

            $sheetDataXml = '';
            foreach ($rows as $rowIdx => $cells) {
                $rNum = $rowIdx + 1;
                $rowXml = '';
                foreach ($cells as $colIdx => $val) {
                    $colLetter = chr(ord('A') + $colIdx);
                    $escapedVal = htmlspecialchars($val, ENT_XML1);
                    $rowXml .= "<c r=\"{$colLetter}{$rNum}\" t=\"inlineStr\"><is><t>{$escapedVal}</t></is></c>";
                }
                $sheetDataXml .= "<row r=\"{$rNum}\">{$rowXml}</row>";
            }

            $zip->addFromString("xl/worksheets/sheet{$sheetIdx}.xml", '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                ."<sheetData>{$sheetDataXml}</sheetData>"
                .'</worksheet>');

            $sheetIdx++;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$contentTypesOverrides
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets>{$workbookSheetsXml}</sheets>"
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$workbookRelsXml
            .'</Relationships>');

        $zip->close();
        $contents = (string) file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $contents;
    }
}
