const files = require.context(path, true, /^Vl[a-zA-Z0-9]+\.vue$/i)

files.keys().map(key => {
    Vue.component('Vl'+key.split('/').pop().split('.')[0], files(key).default)
})