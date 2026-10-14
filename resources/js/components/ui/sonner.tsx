import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster(props: ToasterProps) {
    return (
        <Sonner
            position="top-center"
            offset={{ top: 'max(env(safe-area-inset-top), 12px)' }}
            mobileOffset={{ top: 'max(env(safe-area-inset-top), 12px)' }}
            toastOptions={{
                classNames: {
                    toast: '!rounded-2xl !border-linha !bg-white !text-texto !shadow-lg !font-sans',
                    description: '!text-suave',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
