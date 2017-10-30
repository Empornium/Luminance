module.exports = function(grunt) {

  grunt.initConfig({
    svgstore: {
      options: {
        prefix : '',
      },
      nav_icons : {
        files: {
          'nav_icons.svg': ['opt/nav_*.svg'],
        }
      },
      forum_icons : {
        files: {
          'forum_icons.svg': ['opt/forum_*.svg'],
        }
      },
      misc_icons : {
        files: {
          'misc_icons.svg': ['opt/misc_*.svg'],
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-svgstore');

  grunt.registerTask('default', ['svgstore']);

};

