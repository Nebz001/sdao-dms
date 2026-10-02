import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const alertVariants = cva(
  "relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-background text-foreground",
        // One tinted recipe for every tone: a thin border and a very light
        // tint of the tone, the icon in the tone's "-foreground" color, and
        // the details a step softer than full foreground. The text keeps
        // 4.5:1 or better on its own tint in both themes; the icon and the
        // tint carry the tone, so color is never the only signal (PageNotice
        // also prints a bold lead sentence).
        destructive:
          "border-destructive/40 bg-destructive/10 [&>svg]:text-destructive-foreground *:data-[slot=alert-title]:text-destructive-foreground *:data-[slot=alert-description]:text-foreground/75",
        success:
          "border-success/40 bg-success/10 [&>svg]:text-success-foreground *:data-[slot=alert-title]:text-success-foreground *:data-[slot=alert-description]:text-foreground/75",
        warning:
          "border-warning/40 bg-warning/10 [&>svg]:text-warning-foreground *:data-[slot=alert-title]:text-warning-foreground *:data-[slot=alert-description]:text-foreground/75",
        info: "border-info/40 bg-info/10 [&>svg]:text-info-foreground *:data-[slot=alert-title]:text-info-foreground *:data-[slot=alert-description]:text-foreground/75",
        neutral:
          "border-muted-foreground/40 bg-muted-foreground/10 [&>svg]:text-foreground/75 *:data-[slot=alert-title]:text-foreground *:data-[slot=alert-description]:text-foreground/75",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Alert({
  className,
  variant,
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
  return (
    <div
      data-slot="alert"
      role="alert"
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-title"
      className={cn(
        "col-start-2 line-clamp-1 min-h-4 font-semibold tracking-tight",
        className
      )}
      {...props}
    />
  )
}

function AlertDescription({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        "text-muted-foreground col-start-2 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed",
        className
      )}
      {...props}
    />
  )
}

export { Alert, AlertTitle, AlertDescription }
