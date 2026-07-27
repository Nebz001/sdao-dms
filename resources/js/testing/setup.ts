import '@testing-library/jest-dom/vitest';

// jsdom doesn't implement ResizeObserver. Radix components that measure
// themselves (e.g. Checkbox's hidden form-bubble input, sized to match its
// visible control) call it unconditionally, so any test that mounts one
// inside a real <form> needs this stub — without it the effect throws
// before the component finishes mounting.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}

globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver;
