import { describe, expect, it } from 'vitest';
import { didDraftSave } from '@/lib/draft-save';

function reply(body: BodyInit | null, init: ResponseInit & { redirected?: boolean } = {}): Response {
    const { redirected, ...rest } = init;
    const response = new Response(body, rest);

    if (redirected) {
        Object.defineProperty(response, 'redirected', { value: true });
    }

    return response;
}

const json = { 'Content-Type': 'application/json' };

describe('didDraftSave', () => {
    it('is true only for a JSON reply that says it saved', async () => {
        expect(await didDraftSave(reply(JSON.stringify({ saved: true }), { headers: json }))).toBe(true);
    });

    it('is false for an error status', async () => {
        expect(await didDraftSave(reply(JSON.stringify({ saved: true }), { status: 500, headers: json }))).toBe(false);
        expect(await didDraftSave(reply(JSON.stringify({ message: 'no' }), { status: 422, headers: json }))).toBe(false);
    });

    it('is false for a followed redirect, which is how a validation failure or expired session arrives', async () => {
        expect(await didDraftSave(reply('<html></html>', { status: 200, redirected: true, headers: { 'Content-Type': 'text/html' } }))).toBe(false);
        expect(await didDraftSave(reply(JSON.stringify({ saved: true }), { redirected: true, headers: json }))).toBe(false);
    });

    it('is false for a page that is not JSON', async () => {
        expect(await didDraftSave(reply('<html>Redirecting</html>', { headers: { 'Content-Type': 'text/html' } }))).toBe(false);
    });

    it('is false for JSON without the saved flag, or with it false, or unreadable', async () => {
        expect(await didDraftSave(reply(JSON.stringify({}), { headers: json }))).toBe(false);
        expect(await didDraftSave(reply(JSON.stringify({ saved: false }), { headers: json }))).toBe(false);
        expect(await didDraftSave(reply('not json', { headers: json }))).toBe(false);
    });
});
