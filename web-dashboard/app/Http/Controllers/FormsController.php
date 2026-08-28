<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use Illuminate\Http\Request;

/**
 * Forms OCR tracking: log received forms (scan/upload), OCR them for a
 * searchable record, and move them through Pending Approval -> Approved ->
 * Completed. Access is gated by the `perm:forms,*` route middleware.
 */
class FormsController extends Controller
{
    /** The workflow statuses, in order (also enforced by the middleware). */
    public const STATUSES = ['Pending Approval', 'Approved', 'Completed'];

    public function index(Request $request, MiddlewareClient $client)
    {
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $forwarding = $request->query('forwarding') === '1';

        $result = $client->forms($status, $q);
        $forms = $result['data'] ?? [];

        // Count active email forwards (Form 10) and optionally filter to them.
        $forwardingCount = collect($forms)->filter(fn ($f) => self::forwardingActive($f))->count();
        if ($forwarding) {
            $forms = array_values(array_filter($forms, fn ($f) => self::forwardingActive($f)));
        }

        return view('forms.index', [
            'forms'           => $forms,
            'counts'          => $result['counts'] ?? [],
            'statuses'        => self::STATUSES,
            'status'          => $status,
            'q'               => $q,
            'forwarding'      => $forwarding,
            'forwardingCount' => $forwardingCount,
            'error'           => $client->lastError,
        ]);
    }

    /**
     * Is a temporary email forward still active on this form? True when
     * forwarding is on, not marked done, and the "until" date hasn't passed
     * (a missing date counts as open-ended). Auto-clears once the date passes.
     */
    public static function forwardingActive(array $form): bool
    {
        if (empty($form['email_forwarding']) || ! empty($form['forwarding_done'])) {
            return false;
        }
        $until = $form['forward_until'] ?? null;
        if (! $until) {
            return true; // forwarding on, no end date set
        }
        try {
            return \Illuminate\Support\Carbon::parse($until)->endOfDay()->isFuture();
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function create()
    {
        return view('forms.create');
    }

    public function store(Request $request, MiddlewareClient $client)
    {
        $data = $request->validate([
            'form_type'     => 'nullable|string|max:120',
            'reference_no'  => 'nullable|string|max:150',
            'received_date' => 'nullable|date',
            'from_party'    => 'nullable|string|max:200',
            'company'       => 'nullable|string|max:200',
            'remarks'       => 'nullable|string',
            'email_forwarding' => 'nullable|boolean',
            'forward_to'    => 'nullable|string|max:200',
            'forward_until' => 'nullable|date',
            'images'        => 'required|array|min:1',
            'images.*'      => 'file|mimes:jpeg,jpg,png,gif,webp,bmp,pdf|max:51200', // 50 MB, matches middleware
        ], [
            'images.required' => 'Attach at least one scan, photo or PDF of the form.',
            'images.*.mimes'  => 'Each file must be an image (JPG, PNG, GIF, WebP, BMP) or a PDF.',
        ]);

        $formType = $this->resolveFormType($request);
        // Email forwarding only applies to Form 10 — ignore it on any other form.
        $isForm10 = \Illuminate\Support\Str::contains((string) $formType, 'Form 10');

        $fields = [
            'form_type'     => $formType,
            'reference_no'  => $data['reference_no'] ?? null,
            'received_date' => $data['received_date'] ?? null,
            'from_party'    => $data['from_party'] ?? null,
            'company'       => $data['company'] ?? null,
            'remarks'       => $data['remarks'] ?? null,
            'email_forwarding' => ($isForm10 && $request->boolean('email_forwarding')) ? 1 : 0,
            'forward_to'    => $isForm10 ? ($data['forward_to'] ?? null) : null,
            'forward_until' => $isForm10 ? ($data['forward_until'] ?? null) : null,
            'created_by'    => session('glpi_user'),
        ];
        $fields = array_filter($fields, fn ($v) => $v !== null);

        $id = $client->createForm($fields, (array) $request->file('images', []));

        if ($id === null) {
            return back()->withInput()->with('error', $client->lastError ?? 'Could not save the form.');
        }

        return redirect()->route('forms.show', ['id' => $id])->with('success', 'Form logged as Pending Approval.');
    }

    public function show(int $id, MiddlewareClient $client)
    {
        $form = $client->form($id);
        if ($form === null) {
            return redirect()->route('forms.index')->with('error', 'Form not found.');
        }

        return view('forms.show', [
            'form'     => $form,
            'history'  => $client->formHistory($id),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, int $id, MiddlewareClient $client)
    {
        $data = $request->validate([
            'form_type'     => 'nullable|string|max:120',
            'reference_no'  => 'nullable|string|max:150',
            'received_date' => 'nullable|date',
            'from_party'    => 'nullable|string|max:200',
            'company'       => 'nullable|string|max:200',
            'remarks'       => 'nullable|string',
            'status'        => 'nullable|in:'.implode(',', self::STATUSES),
            'note'          => 'nullable|string|max:255',
            'forward_to'    => 'nullable|string|max:200',
            'forward_until' => 'nullable|date',
        ]);

        $data['form_type'] = $this->resolveFormType($request);
        $data['actor'] = session('glpi_user');
        // Email forwarding only applies to Form 10 — force it off on any other form.
        $isForm10 = \Illuminate\Support\Str::contains((string) $data['form_type'], 'Form 10');
        $data['email_forwarding'] = ($isForm10 && $request->boolean('email_forwarding')) ? 1 : 0;
        $data['forwarding_done'] = ($isForm10 && $request->boolean('forwarding_done')) ? 1 : 0;
        if (! $isForm10) {
            $data['forward_to'] = null;
            $data['forward_until'] = null;
        }

        $payload = array_filter($data, fn ($v) => $v !== null);
        // Keep the boolean fields even when 0 (array_filter drops 0/false-y).
        $payload['email_forwarding'] = $data['email_forwarding'];
        $payload['forwarding_done'] = $data['forwarding_done'];

        $ok = $client->updateForm($id, $payload);

        if (! $ok) {
            return back()->withInput()->with('error', $client->lastError ?? 'Could not update the form.');
        }

        $redirect = redirect()->route('forms.show', ['id' => $id])->with('success', 'Form updated.');
        // WARN-ONLY completion gate: surface a middleware warning without blocking.
        return $client->lastWarning ? $redirect->with('warning', $client->lastWarning) : $redirect;
    }

    /** Re-run the OCR + signature pipeline on the stored files. */
    public function reprocess(int $id, MiddlewareClient $client)
    {
        $ok = $client->reprocessForm($id);

        return $ok
            ? redirect()->route('forms.show', ['id' => $id])->with('success', 'OCR & signature detection re-run.')
            : back()->with('error', $client->lastError ?? 'Could not reprocess the form.');
    }

    /** Add a timestamped IT note to a form. */
    public function addNote(Request $request, int $id, MiddlewareClient $client)
    {
        $data = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $ok = $client->addFormNote($id, trim($data['note']), session('glpi_user'));

        return $ok
            ? redirect()->route('forms.show', ['id' => $id])->with('success', 'Note added.')->withFragment('notes')
            : back()->withInput()->with('error', $client->lastError ?? 'Could not add the note.');
    }

    public function destroy(int $id, MiddlewareClient $client)
    {
        $ok = $client->deleteForm($id, session('glpi_user'));

        return $ok
            ? redirect()->route('forms.index')->with('success', 'Form deleted (snapshot kept in the Audit Trail).')
            : back()->with('error', $client->lastError ?? 'Could not delete the form.');
    }

    /**
     * The Form type comes from a dropdown of known templates, or the free-text
     * "Other" box when the user picked "Other". Returns the effective value.
     */
    protected function resolveFormType(Request $request): ?string
    {
        $type = trim((string) $request->input('form_type', ''));
        if ($type === '' || strcasecmp($type, 'Other') === 0) {
            $other = trim((string) $request->input('form_type_other', ''));
            return $other !== '' ? $other : null;
        }
        return $type;
    }
}
