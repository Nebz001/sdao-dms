import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';

type Props = {
    children: ReactNode;
    /** Where the "back" link goes, so a broken page is never a dead end. */
    backHref?: string;
    backLabel?: string;
};

type State = { error: Error | null };

/**
 * Catches an error thrown while rendering its children and shows a readable
 * message instead of an empty page. Cleared by "Try again" or by navigating.
 */
export default class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('Render error caught by ErrorBoundary:', error, info.componentStack);
    }

    render() {
        if (this.state.error === null) {
            return this.props.children;
        }

        return (
            <Empty role="alert">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <TriangleAlert />
                    </EmptyMedia>
                    <EmptyTitle>This page could not be displayed</EmptyTitle>
                    <EmptyDescription>
                        Something went wrong while drawing this page. Try again, or go back and open it from the list.
                    </EmptyDescription>
                </EmptyHeader>
                <div className="flex flex-wrap items-center justify-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => this.setState({ error: null })}>
                        Try again
                    </Button>
                    {this.props.backHref && (
                        <Button asChild size="sm">
                            <Link href={this.props.backHref}>{this.props.backLabel ?? 'Go back'}</Link>
                        </Button>
                    )}
                </div>
            </Empty>
        );
    }
}
