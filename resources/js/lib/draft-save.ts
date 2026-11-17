/**
 * Whether a draft-save `fetch` really saved.
 *
 * The web app renders a validation failure (and an expired session) as a
 * redirect, and only `api/*` gets JSON, so a plain `fetch` follows the
 * redirect to an ordinary page and `response.ok` is true even though nothing
 * was saved. A real save answers with JSON `{ saved: true }`; anything else
 * (an error status, a followed redirect, an HTML page, a body without the
 * flag) counts as failed, so a lost draft is never reported as saved.
 */
export async function didDraftSave(response: Response): Promise<boolean> {
    if (!response.ok || response.redirected) {
        return false;
    }

    if (!(response.headers.get('content-type') ?? '').includes('application/json')) {
        return false;
    }

    try {
        const body: unknown = await response.json();

        return typeof body === 'object' && body !== null && (body as { saved?: unknown }).saved === true;
    } catch {
        return false;
    }
}
