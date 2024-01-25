module.exports = function(grunt) {

  grunt.initConfig({
    webfont: {
      icons: {
        src: 'svg/*.svg',
        dest: '../../public/static/common/',
        options: {
          font: 'icons',
          embed: false,
          htmlDemo: false,
          types: 'woff',
          relativeFontPath: '/static/common/',
          template: 'font_template.css',
          engine: 'fontforge',
          normalize: true,
          destCss: '../../public/static/common/',
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-webfont');
  grunt.registerTask('default', ['webfont']);

};
