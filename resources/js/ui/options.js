/**
 * Read a JSON options object from a data attribute, e.g. data-tom-select='{"create":true}'.
 */
export function readOptions(element, attribute) {
    const raw = element.getAttribute(attribute);

    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw);
    } catch {
        console.warn(`Invalid JSON in ${attribute}`, element);

        return {};
    }
}
