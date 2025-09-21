/**
 * Minimal dynamic block wrapper that delegates render to the server callback.
 * Keeps editor lightweight while still allowing Inspector controls (future).
 */
/* global wp */
wp.blocks.registerBlockType('mvwp/viewer', {
  title: 'Molecule Viewer',
  icon: 'visibility',
  category: 'widgets',
  edit: () => wp.element.createElement('p', null, 'Molecule Viewer (server-rendered)'),
  save: () => null // dynamic
});
