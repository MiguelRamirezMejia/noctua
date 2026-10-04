import { describe, expect, it } from 'vite-plus/test';
import { accentVariables, readableOn } from '@/lib/accent';

describe('accent', () => {
    it('elige texto blanco sobre colores oscuros y negro sobre claros', () => {
        expect(readableOn('#2563eb')).toBe('#ffffff');
        expect(readableOn('#facc15')).toBe('#111111');
        expect(readableOn('#ffffff')).toBe('#111111');
        expect(readableOn('#000000')).toBe('#ffffff');
    });

    it('genera las mismas variables que el servidor', () => {
        expect(accentVariables('#2563EB')).toEqual({
            '--primary': '#2563eb',
            '--primary-foreground': '#ffffff',
            '--ring': '#2563eb',
            '--sidebar-primary': '#2563eb',
            '--sidebar-primary-foreground': '#ffffff',
            '--sidebar-ring': '#2563eb',
            '--accent': 'color-mix(in oklab, #2563eb 14%, var(--background))',
            '--sidebar-accent':
                'color-mix(in oklab, #2563eb 16%, var(--sidebar))',
        });
    });
});
