/**
 * Utility functions for Package Messenger & AI
 */

export const avatarPalettes = [
  'linear-gradient(135deg, #4f46e5, #3730a3)',
  'linear-gradient(135deg, #0284c7, #0369a1)',
  'linear-gradient(135deg, #059669, #047857)',
  'linear-gradient(135deg, #d97706, #b45309)',
  'linear-gradient(135deg, #db2777, #be185d)',
  'linear-gradient(135deg, #7c3aed, #6d28d9)',
  'linear-gradient(135deg, #ea580c, #c2410c)',
];

export function getAvatarGradient(name) {
  if (!name) return avatarPalettes[0];
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  const idx = Math.abs(hash) % avatarPalettes.length;
  return avatarPalettes[idx];
}

export function getInitials(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
}

export function escapeHtml(text) {
  if (!text) return '';
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return String(text).replace(/[&<>"']/g, (m) => map[m]);
}

export function formatTime(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString.replace(' ', 'T'));
  if (isNaN(d.getTime())) return isoString;
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export function formatRelativeDate(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString.replace(' ', 'T'));
  if (isNaN(d.getTime())) return isoString;
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const msgDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diffDays = Math.round((today - msgDate) / (1000 * 60 * 60 * 24));
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) {
    return d.toLocaleDateString([], { weekday: 'short' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

export function formatMarkdown(text) {
  if (!text) return '';
  let escaped = escapeHtml(text);
  escaped = escaped.replace(/^### (.+)$/gm, '<h6 class="fw-bold mb-1 mt-2">$1</h6>');
  escaped = escaped.replace(/^## (.+)$/gm, '<h5 class="fw-bold mb-1 mt-2">$1</h5>');
  escaped = escaped.replace(/^# (.+)$/gm, '<h4 class="fw-bold mb-1 mt-2">$1</h4>');
  escaped = escaped.replace(/^---+$/gm, '<hr class="my-2">');
  escaped = escaped.replace(/^\* (.+)$/gm, '• $1');
  escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
  escaped = escaped.replace(/`([^`]+)`/g, '<code class="bg-light px-1 rounded text-dark">$1</code>');
  escaped = escaped.replace(/\n/g, '<br>');
  return escaped;
}

