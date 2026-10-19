import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

type LabelProps = React.ComponentProps<typeof LabelPrimitive.Root> & {
  /**
   * Draws the red required asterisk. The mark is decorative and hidden from
   * screen readers; the control itself carries aria-required.
   */
  required?: boolean
}

/** The red asterisk. Decorative: aria-hidden, so it is never read out as "star". */
function RequiredMark() {
  return (
    <span
      aria-hidden="true"
      data-slot="required-mark"
      className="ml-0.5 text-destructive after:content-['*']"
    />
  )
}

function Label({
  className,
  required = false,
  children,
  ...props
}: LabelProps) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required && <RequiredMark />}
    </LabelPrimitive.Root>
  )
}

export { Label, RequiredMark }
