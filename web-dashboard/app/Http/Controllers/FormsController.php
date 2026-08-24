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

        $result = $client->forms($status, $q);

        return view('forms.index', [
            'forms'    => $result['data'] ?? [],
            'counts'   => $result['counts'] ?? [],
            'statuses' => self::STATUSES,
            'status'   => $status,
            'q'        => $q,
            'error'    => $client->lastError,
        ]);
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
            'images'        => 'required|array|min:1',
            'images.*'      => 'file|mimes:jpeg,jpg,png,gif,webp,bmp,pdf|max:51200', // 50 MB, matches middleware
        ], [
            'images.required' => 'Attach at least one scan, photo or PDF of the form.',
            'images.*.mimes'  => 'Each file must be an image (JPG, PNG, GIF, WebP, BMP) or a PDF.',
        ]);

        $fields = [
            'form_type'     => $this->resolveFormType($request),
            'reference_no'  => $data['reference_no'] ?? null,
            'received_date' => $data['received_date'] ?? null,
            'from_party'    => $data['from_party'] ?? null,
            'company'       => $data['company'] ?? null,
            'remarks'       => $data['remarks'] ?? null,
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
        ]);

        $data['form_type'] = $this->resolveFormType($request);
        $data['actor'] = session('glpi_user');

        $ok = $client->updateForm($id, array_filter($data, fn ($v) => $v !== null));

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
