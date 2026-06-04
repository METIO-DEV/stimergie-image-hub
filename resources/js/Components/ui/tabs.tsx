import { cn } from "@/lib/utils";
import { createContext, ReactNode, useContext } from "react";

type TabsContextValue = {
    value: string;
    onValueChange: (value: string) => void;
};

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabs() {
    const context = useContext(TabsContext);

    if (!context) {
        throw new Error("Tabs components must be used inside Tabs.");
    }

    return context;
}

function Tabs({
    value,
    onValueChange,
    children,
    className,
}: {
    value: string;
    onValueChange: (value: string) => void;
    children: ReactNode;
    className?: string;
}) {
    return (
        <TabsContext.Provider value={{ value, onValueChange }}>
            <div className={className}>{children}</div>
        </TabsContext.Provider>
    );
}

function TabsList({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            role="tablist"
            className={cn(
                "flex border-b border-border text-muted-foreground",
                className,
            )}
        >
            {children}
        </div>
    );
}

function TabsTrigger({
    value,
    children,
    className,
}: {
    value: string;
    children: ReactNode;
    className?: string;
}) {
    const tabs = useTabs();
    const selected = tabs.value === value;
    const focusSibling = (current: HTMLButtonElement, offset: number) => {
        const triggers = Array.from(
            current.parentElement?.querySelectorAll<HTMLButtonElement>(
                '[role="tab"]',
            ) ?? [],
        );
        const currentIndex = triggers.indexOf(current);
        const next =
            triggers[
                (currentIndex + offset + triggers.length) % triggers.length
            ];

        next?.focus();
        next?.click();
    };

    return (
        <button
            type="button"
            role="tab"
            aria-selected={selected}
            aria-controls={`${value}-panel`}
            id={`${value}-tab`}
            tabIndex={selected ? 0 : -1}
            className={cn(
                "-mb-px inline-flex h-11 items-center justify-center whitespace-nowrap border-b-2 px-4 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
                selected
                    ? "border-primary text-foreground"
                    : "border-transparent hover:border-border hover:text-foreground",
                className,
            )}
            onKeyDown={(event) => {
                if (event.key === "ArrowRight") {
                    event.preventDefault();
                    focusSibling(event.currentTarget, 1);
                }

                if (event.key === "ArrowLeft") {
                    event.preventDefault();
                    focusSibling(event.currentTarget, -1);
                }
            }}
            onClick={() => tabs.onValueChange(value)}
        >
            {children}
        </button>
    );
}

function TabsContent({
    value,
    children,
    className,
}: {
    value: string;
    children: ReactNode;
    className?: string;
}) {
    const tabs = useTabs();
    const selected = tabs.value === value;

    return (
        <div
            role="tabpanel"
            id={`${value}-panel`}
            aria-labelledby={`${value}-tab`}
            hidden={!selected}
            className={cn("mt-6", className)}
        >
            {children}
        </div>
    );
}

export { Tabs, TabsContent, TabsList, TabsTrigger };
