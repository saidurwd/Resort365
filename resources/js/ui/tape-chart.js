/**
 * Tape chart (Reservations): drag a booking bar onto another room's row. The server decides
 * (MoveOnChart); on success the page reloads with the new layout, on refusal the bar stays
 * where it was and the reason is shown.
 */
export function tapeChart({ moveUrl, canMove }) {
    return {
        dragging: null,
        target: null,
        busy: false,
        message: '',
        ok: true,

        start(event, itemId) {
            if (!canMove || this.busy) {
                event.preventDefault();
                return;
            }

            this.dragging = itemId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(itemId));
        },

        end() {
            this.dragging = null;
            this.target = null;
        },

        over(roomId) {
            if (this.dragging !== null) {
                this.target = roomId;
            }
        },

        leave(roomId) {
            if (this.target === roomId) {
                this.target = null;
            }
        },

        async drop(roomId) {
            const itemId = this.dragging;
            this.end();

            if (itemId === null || this.busy) {
                return;
            }

            this.busy = true;
            this.message = '';

            try {
                const response = await fetch(moveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ item_id: itemId, room_id: roomId }),
                });
                const data = await response.json().catch(() => ({}));

                if (response.ok && data.ok) {
                    window.location.reload();
                    return;
                }

                this.ok = false;
                this.message = data.message
                    || Object.values(data.errors ?? {}).flat()[0]
                    || response.statusText;
            } catch {
                this.ok = false;
                this.message = 'The move could not be sent. Check the connection and try again.';
            } finally {
                this.busy = false;
            }
        },
    };
}
