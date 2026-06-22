import { ImgHTMLAttributes, SyntheticEvent, forwardRef } from "react";

import { cn } from "@/lib/utils";

type ProtectedImageProps = ImgHTMLAttributes<HTMLImageElement>;

const blockBrowserImageAction = (event: SyntheticEvent<HTMLImageElement>) => {
    event.preventDefault();
};

export const ProtectedImage = forwardRef<HTMLImageElement, ProtectedImageProps>(
    (
        {
            className,
            draggable = false,
            onContextMenu,
            onDragStart,
            ...props
        },
        ref,
    ) => (
        <img
            {...props}
            ref={ref}
            className={cn(
                "pointer-events-none select-none [-webkit-touch-callout:none] [-webkit-user-drag:none]",
                className,
            )}
            draggable={draggable}
            onContextMenu={(event) => {
                blockBrowserImageAction(event);
                onContextMenu?.(event);
            }}
            onDragStart={(event) => {
                blockBrowserImageAction(event);
                onDragStart?.(event);
            }}
        />
    ),
);

ProtectedImage.displayName = "ProtectedImage";
