// `export *`, not a bare `import`: Flarum reads the extension bundle's named
// exports to find `extend` (the extender array). A side-effect-only import
// runs the initializer but drops every named export, so any extender declared
// in src/ would silently never run. js/admin.ts already did this correctly.
export * from './src/forum/index';
