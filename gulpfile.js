var gulp = require('gulp')
var concat = require('gulp-concat')
var sourcemaps = require('gulp-sourcemaps')
var uglify = require('gulp-uglify-es').default
var ngAnnotate = require('gulp-ng-annotate')
var minifycss = require('gulp-minify-css')
var rename = require('gulp-rename')
var filter = require('gulp-filter')
var templateCache = require('gulp-angular-templatecache')
var addStream = require('add-stream')

function prepareTemplates() {
  return gulp.src('app/src/**/*.html')
    //.pipe(minify and preprocess the template html here)
    .pipe(templateCache('templates.js', {root: 'app/src/', module: 'AmpersandApp'}));
}

gulp.task('js', function () {
  gulp.src(['app/src/module.js', 'app/src/**/*.js', 'app/project/**/*.js'])
    .pipe(addStream.obj(prepareTemplates()))
    .pipe(sourcemaps.init())
    .pipe(concat('ampersand.js'))
    .pipe(ngAnnotate())
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('app/dist'))
    .pipe(filter('**/*.js')) // only .js files go through
    .pipe(rename({suffix: '.min'}))
    .pipe(uglify())
    .on('error', function(err){
        console.error(err.toString());
    })
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('app/dist'))
})
gulp.task('css', function () {
    gulp.src(['app/src/module.css', 'app/src/**/*.css', 'app/project/**/*.css'])
      .pipe(concat('ampersand.css'))
      .pipe(gulp.dest('app/dist'))
      .pipe(rename({suffix: '.min'}))
      .pipe(minifycss())
      .pipe(gulp.dest('app/dist'))
  })

gulp.task('watch', ['js'], function () {
  gulp.watch(['app/src/**/*.js', 'app/js/**/*.js'], ['js'])
})

gulp.task('default', ['css', 'js'])