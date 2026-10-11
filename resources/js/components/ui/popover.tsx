import * as PopoverPrimitive from "@radix-ui/react-popover"
import * as React from "react"

import { cn } from "@/lib/utils"

function Popover({
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Root>) {
  return <PopoverPrimitive.Root data-slot="popover" {...props} />
}

function PopoverTrigger({
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Trigger>) {
  return <PopoverPrimitive.Trigger data-slot="popover-trigger" {...props} />
}

function PopoverAnchor({
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Anchor>) {
  return <PopoverPrimitive.Anchor data-slot="popover-anchor" {...props} />
}

function PopoverContent({
  className,
  align = "center",
  sideOffset = 4,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Content>) {
  return (
    <PopoverPrimitive.Portal>
      <PopoverPrimitive.Content
        data-slot="popover-content"
        align={align}
        sideOffset={sideOffset}
        className={cn(
          "bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 z-50 w-72 rounded-md border p-4 shadow-md outline-hidden",
          className
        )}
        {...props}
      />
    </PopoverPrimitive.Portal>
  )
}

/**
 * A small pointer on the edge of the card toward its anchor, drawn with the
 * card's own border and background. Radix turns it around when the card flips
 * to the other side of the anchor.
 */
function PopoverArrow({
  className,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Arrow>) {
  return (
    <PopoverPrimitive.Arrow asChild {...props}>
      <svg
        data-slot="popover-arrow"
        aria-hidden
        width={16}
        height={8}
        viewBox="0 0 20 10"
        preserveAspectRatio="none"
        className={cn("overflow-visible", className)}
      >
        <rect x={1} y={-1.5} width={18} height={1.5} className="fill-popover" />
        <path d="M0 0 L10 10 L20 0 Z" className="fill-popover" />
        <path
          d="M0 0 L10 10 L20 0"
          fill="none"
          strokeWidth={1.25}
          className="stroke-border"
        />
      </svg>
    </PopoverPrimitive.Arrow>
  )
}

export { Popover, PopoverTrigger, PopoverContent, PopoverAnchor, PopoverArrow }
