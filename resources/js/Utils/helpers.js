/**
 * Get initials from a full name string.
 * @param {string} name
 * @returns {string}
 */
export function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}
