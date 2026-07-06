/**
 * Realtime seam — Supabase swap point (Slice 6).
 *
 * This file documents the Supabase channel contract so the swap is localized.
 * Current implementation: Inertia polling via useDocumentUpdates hook.
 *
 * When wiring Supabase Realtime:
 *   1. Install @supabase/supabase-js
 *   2. Expose VITE_SUPABASE_URL and VITE_SUPABASE_ANON_KEY from .env
 *   3. Replace useDocumentUpdates hook body with:
 *
 *      const client = createClient(
 *        import.meta.env.VITE_SUPABASE_URL,
 *        import.meta.env.VITE_SUPABASE_ANON_KEY,
 *      );
 *      client.channel('documents')
 *        .on('postgres_changes', { event: '*', schema: 'public', table: 'documents' }, () => {
 *          router.reload({ only: ['document', 'history', 'queue'] });
 *        })
 *        .subscribe();
 *      return () => { client.channel('documents').unsubscribe(); };
 *
 *   4. Add `documents` and `document_transitions` to the supabase_realtime publication
 *      (Laravel remains the sole write path; engine already persists every status/transition).
 */
export {};
