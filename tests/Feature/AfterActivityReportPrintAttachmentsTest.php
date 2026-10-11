<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\User;
use App\Printing\AfterActivityReportForm;
use App\Printing\PrintAttachmentLoader;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The After Activity Report's printed form embeds the officer's uploads:
 * photos as real images, the evaluation form and attendance sheet as
 * appendix pages (images rendered, PDFs merged, anything else a note).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    Storage::fake('supabase');

    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $calendarDocument = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'organization_id' => $org->id,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDocument->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Print Attachments Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
    $proposalDocument = Document::create([
        'form_type' => FormType::ActivityProposal,
        'title' => 'Approved Proposal',
        'status' => DocumentStatus::Approved,
        'organization_id' => $org->id,
    ]);
    $proposal = ActivityProposal::factory()->create([
        'document_id' => $proposalDocument->id,
        'calendar_activity_id' => $activity->id,
        'calendar_mode' => 'on_calendar',
    ]);

    $this->report = Document::create([
        'form_type' => FormType::AfterActivityReport,
        'title' => 'After-Activity Report',
        'status' => DocumentStatus::Draft,
        'organization_id' => $org->id,
        'submitted_by' => $this->student->id,
    ]);
    AfterActivityReport::create([
        'document_id' => $this->report->id,
        'activity_proposal_id' => $proposal->id,
        'summary' => 'It went well.',
        'activity_chairs' => ['Chair One'],
        'prepared_by' => 'Preparer',
        'event_program' => 'Opening, closing.',
        'target_participants_percentage' => 80,
    ]);
});

function printAttach(Document $document, string $slot, string $filename, string $contents, string $mime): DocumentAttachment
{
    $path = "attachments/after_activity_report/{$document->id}/".uniqid().'-'.$filename;
    Storage::disk('supabase')->put($path, $contents);

    return DocumentAttachment::create([
        'document_id' => $document->id,
        'slot_key' => $slot,
        'original_filename' => $filename,
        'path' => $path,
        'disk' => 'supabase',
        'mime_type' => $mime,
        'size' => strlen($contents),
        'uploaded_by' => null,
    ]);
}

function printJpeg(int $width = 400, int $height = 300, int $red = 200): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, $red, 40, 40));
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function printPdf(int $pages): string
{
    $pdf = new FPDF;

    for ($i = 1; $i <= $pages; $i++) {
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->Cell(0, 10, "Uploaded page {$i}");
    }

    return $pdf->Output('S');
}

function printPageCount(string $pdf): int
{
    return preg_match_all('/\/Type\s*\/Page(?![s\w])/', $pdf);
}

function printImageCount(string $pdf): int
{
    return preg_match_all('/\/Subtype\s*\/Image/', $pdf);
}

function printedReport(Document $document, User $user): string
{
    $response = test()->actingAs($user)->get(route('documents.print', $document));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    return $response->getContent();
}

function reportHtml(Document $document): string
{
    $form = app(AfterActivityReportForm::class);
    $document->load($form->eagerLoads());

    return view($form->view(), $form->data($document) + $form->assets($document))->render();
}

test('photos render as embedded images, not just file names', function () {
    $baseline = printImageCount(printedReport($this->report, $this->student));

    printAttach($this->report, 'photos', 'one.jpg', printJpeg(), 'image/jpeg');
    printAttach($this->report, 'photos', 'two.jpg', printJpeg(300, 500, 140), 'image/jpeg');
    printAttach($this->report, 'photos', 'three.jpg', printJpeg(400, 300, 90), 'image/jpeg');

    expect(printImageCount(printedReport($this->report->fresh(), $this->student)))->toBe($baseline + 3);
    expect(reportHtml($this->report->fresh()))
        ->toContain('data:image/jpeg;base64,')
        ->toContain('one.jpg')
        ->toContain('three.jpg');
});

test('a large phone photo is downscaled before it is embedded', function () {
    printAttach($this->report, 'photos', 'huge.jpg', printJpeg(4000, 3000), 'image/jpeg');

    $html = reportHtml($this->report->fresh());
    preg_match('/data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)/', $html, $match);
    [$width, $height] = getimagesizefromstring(base64_decode($match[1]));

    expect(max($width, $height))->toBeLessThanOrEqual(1400);
});

test('the main form text keeps its lines and points to the appendices', function () {
    printAttach($this->report, 'evaluation_form', 'eval.jpg', printJpeg(), 'image/jpeg');
    printAttach($this->report, 'attendance_sheet', 'attendance.jpg', printJpeg(), 'image/jpeg');

    expect(reportHtml($this->report->fresh()))
        ->toContain('Sample Evaluation Form: Attached (see Appendix A).')
        ->toContain('Attendance Sheet: Attached (see Appendix B).');

    $this->report->attachments()->delete();

    expect(reportHtml($this->report->fresh()))
        ->toContain('Sample Evaluation Form: ')
        ->not->toContain('Attached');
});

test('an image upload gets its own appendix page', function () {
    $baseline = printedReport($this->report, $this->student);

    printAttach($this->report, 'evaluation_form', 'eval.jpg', printJpeg(), 'image/jpeg');
    $withImage = printedReport($this->report->fresh(), $this->student);

    expect(printPageCount($withImage))->toBe(printPageCount($baseline) + 1)
        ->and(printImageCount($withImage))->toBe(printImageCount($baseline) + 1);
});

test('an uploaded PDF has its real pages merged after the main form', function () {
    $baseline = printPageCount(printedReport($this->report, $this->student));

    printAttach($this->report, 'evaluation_form', 'eval.pdf', printPdf(3), 'application/pdf');
    printAttach($this->report, 'attendance_sheet', 'attendance.pdf', printPdf(2), 'application/pdf');

    // Each appendix: one heading page + the uploaded pages.
    expect(printPageCount(printedReport($this->report->fresh(), $this->student)))
        ->toBe($baseline + (1 + 3) + (1 + 2));
});

test('a type that cannot be rendered prints a note instead of failing', function () {
    $baseline = printPageCount(printedReport($this->report, $this->student));

    printAttach($this->report, 'evaluation_form', 'eval.docx', 'PK not really a docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $withNote = printPageCount(printedReport($this->report->fresh(), $this->student));

    expect($withNote)->toBe($baseline + 1);

    $html = view('print.appendix', [
        'letter' => 'A',
        'title' => 'Sample Evaluation Form',
        'file' => ['kind' => 'unsupported', 'filename' => 'eval.docx'],
    ])->render();

    expect($html)
        ->toContain('Appendix A: Sample Evaluation Form')
        ->toContain('eval.docx')
        ->toContain('It can be viewed in the system.');
});

test('a PDF that cannot be merged falls back to the note instead of failing', function () {
    printAttach($this->report, 'attendance_sheet', 'broken.pdf', '%PDF-1.4 garbage', 'application/pdf');

    printedReport($this->report->fresh(), $this->student);

    $appendix = app(AfterActivityReportForm::class)->appendices($this->report->fresh())[0];
    $file = app(PrintAttachmentLoader::class)->resolve($appendix->attachment, 190, 225);

    expect($file['kind'])->toBe('unsupported');
});

test('a missing file does not crash the print and is logged', function () {
    $photo = printAttach($this->report, 'photos', 'gone.jpg', printJpeg(), 'image/jpeg');
    $evaluation = printAttach($this->report, 'evaluation_form', 'gone-eval.jpg', printJpeg(), 'image/jpeg');
    Storage::disk('supabase')->delete([$photo->path, $evaluation->path]);

    Log::spy();

    printedReport($this->report->fresh(), $this->student);

    expect(reportHtml($this->report->fresh()))->toContain('File could not be loaded: gone.jpg');
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Print: attachment'))->atLeast()->once();

    $html = view('print.appendix', [
        'letter' => 'A',
        'title' => 'Sample Evaluation Form',
        'file' => ['kind' => 'missing', 'filename' => 'gone-eval.jpg'],
    ])->render();
    expect($html)->toContain('File could not be loaded');
});

test('only this document\'s own attachments are pulled in', function () {
    $other = Document::create([
        'form_type' => FormType::AfterActivityReport,
        'title' => 'Someone else\'s report',
        'status' => DocumentStatus::Draft,
        'organization_id' => $this->report->organization_id,
        'submitted_by' => $this->student->id,
    ]);
    printAttach($other, 'photos', 'not-mine.jpg', printJpeg(), 'image/jpeg');
    printAttach($other, 'evaluation_form', 'not-mine.pdf', printPdf(4), 'application/pdf');

    $form = app(AfterActivityReportForm::class);

    expect($form->assets($this->report->fresh())['photos'])->toBe([])
        ->and($form->appendices($this->report->fresh()))->toBe([]);
});

test('print authorization is unchanged for a user with no access', function () {
    $outsider = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($outsider)->get(route('documents.print', $this->report))->assertForbidden();
});

test('a printed report embeds the logo image and sets the note in italics', function () {
    $pdf = printedReport($this->report, $this->student);

    expect(printImageCount($pdf))->toBeGreaterThanOrEqual(1)
        ->and($pdf)->toContain('Helvetica-Oblique');
});
