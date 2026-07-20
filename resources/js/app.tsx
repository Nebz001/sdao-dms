import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'SDAO-DMS';

// A submission larger than PHP's post_max_size is rejected by the server with a
// clean 413 (see bootstrap/app.php) before any session exists, so it can't be a
// flash toast. Inertia surfaces that non-Inertia response via `httpException`;
// intercept the 413 here, suppress Inertia's default error modal, and show the
// server's friendly message through the same toast system as useFlashToast.
router.on('httpException', (event) => {
    const response = event.detail.response;

    if (response?.status === 413) {
        event.preventDefault();
        const data = response.data;
        const message =
            typeof data === 'object' && data !== null && typeof data.message === 'string'
                ? data.message
                : 'Your files are too large. Please reduce file sizes and try again.';
        toast.error(message);
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    // NOTE: `app` here is the raw, unrendered <App/> element — Inertia's own
    // PageContext.Provider lives INSIDE App's render output, so anything
    // mounted here as a sibling (like Toaster previously was) sits outside
    // that context and crashes any hook needing usePage() (e.g. useFlashToast)
    // during both SSR and client hydration. Toaster is mounted per-layout
    // instead (see app-sidebar-layout.tsx / auth-simple-layout.tsx) — the only
    // two layout templates any real page uses, guaranteed to render inside
    // App's context. TooltipProvider itself needs no Inertia context, so it's
    // fine to stay here.
    withApp(app) {
        return <TooltipProvider delayDuration={0}>{app}</TooltipProvider>;
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
