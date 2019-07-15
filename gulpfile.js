/* jshint node: true */
var gulp = require('gulp')
var concat = require('gulp-concat')
var sourcemaps = require('gulp-sourcemaps')
var uglify = require('gulp-uglify-es').default
var ngAnnotate = require('gulp-ng-annotate')
var cleanCSS = require('gulp-clean-css')
var rename = require('gulp-rename')
var filter = require('gulp-filter')
var templateCache = require('gulp-angular-templatecache')
var addStream = require('add-stream')
var mainBowerFiles = require('gulp-main-bower-files')
var flatten = require('gulp-flatten')
var clean = require('gulp-clean')
const jsValidate = require('gulp-jsvalidate')
const babel = require('gulp-babel');

function prepareTemplates(folder, prefix) {
    return gulp.src(folder + '**/*.html')
        //.pipe(minify and preprocess the template html here)
        .pipe(templateCache('templates.js', { root: prefix, module: 'AmpersandApp' }));
}

/**
 * Gather external library js/css/fonts into dist/lib folder
 */
gulp.task('build-lib', function (done) {
    var filterJS = filter('**/*.js', { restore: true })
    var filterCSS = filter('**/*.css', { restore: true })
    var filterFonts = filter('**/fonts/*.*', { restore: true })

    gulp.src('bower.json') // point to bower.json
        // https://github.com/mauricedb/gulp-main-bower-files
        .pipe(mainBowerFiles({
            overrides: {
                bootstrap: {
                    main: [
                        './dist/js/bootstrap.js',
                        './dist/css/bootstrap.min.css',
                        './dist/fonts/*.*'
                    ]
                }
            }
        }))
        // library javascript
        .pipe(filterJS)
        .pipe(concat('lib.min.js'))
        // don't use babel for vendor libraries. Something strange happens.
        // .pipe(babel({
        //     presets: ['@babel/env']
        // }))
        .pipe(uglify())
        .pipe(gulp.dest('public/app/dist'))
        .pipe(filterJS.restore)
        // library css
        .pipe(filterCSS)
        .pipe(concat('lib.min.css'))
        .pipe(cleanCSS())
        .pipe(gulp.dest('public/app/dist'))
        .pipe(filterCSS.restore)
        // library fonts
        .pipe(filterFonts)
        .pipe(flatten())
        .pipe(gulp.dest('public/app/fonts'))
        .pipe(filterFonts.restore)
    done()
})

/**
 * Gather ampersand js/css/html into dist folder
 */
gulp.task('build-ampersand', function (done) {
    // js
    gulp.src(['app/src/module.js', 'app/src/**/*.js'])
        .pipe(addStream.obj(prepareTemplates('app/src/', 'app/src/')))
        .pipe(sourcemaps.init())
        .pipe(concat('ampersand.js'))
        .pipe(babel({
            presets: ['@babel/env']
        }))
        .pipe(jsValidate())
        .pipe(ngAnnotate())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('public/app/dist'))
        .pipe(filter('**/*.js')) // only .js files go through
        .pipe(rename({ suffix: '.min' }))
        .pipe(uglify())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('public/app/dist'))
    // css
    gulp.src(['app/src/module.css', 'app/src/**/*.css'])
        .pipe(concat('ampersand.css'))
        .pipe(gulp.dest('public/app/dist'))
        .pipe(rename({ suffix: '.min' }))
        .pipe(cleanCSS())
        .pipe(gulp.dest('public/app/dist'))
    done()
})

/**
 * Gulp function to build the application after Ampersand has generated the prototype
 */
gulp.task('build-project', function (done) {
    // css
    gulp.src(['public/app/project/**/*.css', 'public/app/ext/**/*.css', '!public/app/ext/**/lib/**/*'])
        .pipe(concat('project.css'))
        .pipe(gulp.dest('app/dist'))
        .pipe(rename({ suffix: '.min' }))
        .pipe(cleanCSS())
        .pipe(gulp.dest('public/app/dist'))
    // js
    gulp.src(['public/app/project/**/*.js', 'public/app/ext/**/*.js', '!public/app/ext/**/lib/**/*'])
        .pipe(addStream.obj(prepareTemplates('public/app/project/', 'app/project/')))
        .pipe(sourcemaps.init())
        .pipe(concat('project.js'))
        .pipe(babel({
            presets: ['@babel/env']
        }))
        .pipe(jsValidate())
        .pipe(ngAnnotate())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('public/app/dist'))
        .pipe(filter('**/*.js')) // only .js files go through
        .pipe(rename({ suffix: '.min' }))
        .pipe(uglify())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest('public/app/dist'))
    done()
})

gulp.task('clean', function (done) {
    gulp.src('public/app/dist', { read: false, allowEmpty: true })
        .pipe(clean())
    done()
})

gulp.task('dist', gulp.series('clean', 'build-lib', 'build-ampersand'))