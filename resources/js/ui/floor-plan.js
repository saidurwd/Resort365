/**
 * Floor-plan editor (Restaurant setup): drag tables on an area's SVG canvas. Tables are <g data-table>
 * elements placed with a transform attribute (no inline styles); positions snap to the grid and stay
 * inside the canvas. Save posts them as JSON; the server snaps and checks them again (SaveFloorPlan).
 */
export function floorPlan({ url, grid, width, height, editable }) {
    return {
        dragging: null,
        dirty: false,
        busy: false,
        message: '',
        ok: true,

        point(event) {
            const svg = this.$refs.canvas;
            const p = svg.createSVGPoint();
            p.x = event.clientX;
            p.y = event.clientY;

            return p.matrixTransform(svg.getScreenCTM().inverse());
        },

        grab(event) {
            if (!editable || this.busy) {
                return;
            }

            const table = event.currentTarget;
            const p = this.point(event);
            this.dragging = { table, dx: p.x - Number(table.dataset.x), dy: p.y - Number(table.dataset.y) };
            table.classList.add('is-dragging');
            table.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        },

        move(event) {
            if (!this.dragging) {
                return;
            }

            const { table, dx, dy } = this.dragging;
            const p = this.point(event);
            const snap = (value, max) => Math.max(0, Math.min(max, Math.round(value / grid) * grid));
            const x = snap(p.x - dx, width - Number(table.dataset.w));
            const y = snap(p.y - dy, height - Number(table.dataset.h));

            if (x !== Number(table.dataset.x) || y !== Number(table.dataset.y)) {
                table.dataset.x = x;
                table.dataset.y = y;
                table.setAttribute('transform', `translate(${x} ${y})`);
                this.dirty = true;
                this.message = '';
            }
        },

        drop() {
            this.dragging?.table.classList.remove('is-dragging');
            this.dragging = null;
        },

        async save() {
            const positions = [...this.$refs.canvas.querySelectorAll('[data-table]')]
                .map((table) => ({ id: Number(table.dataset.id), x: Number(table.dataset.x), y: Number(table.dataset.y) }));

            if (positions.length === 0) {
                return;
            }

            this.busy = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ positions }),
                });
                const data = await response.json().catch(() => ({}));
                this.ok = response.ok && data.ok;
                this.message = data.message || Object.values(data.errors ?? {}).flat()[0] || response.statusText;

                if (this.ok) {
                    this.dirty = false;
                }
            } catch {
                this.ok = false;
                this.message = 'The floor plan could not be saved. Check the connection and try again.';
            } finally {
                this.busy = false;
            }
        },
    };
}
