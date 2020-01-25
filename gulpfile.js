const gulp = require('gulp');
const sass = require('gulp-sass');
const prefix = require('gulp-autoprefixer');
const rename = require('gulp-rename');
const uglify = require('gulp-uglify');
const plumber = require('gulp-plumber');


gulp.task('styles', (done) => {
  gulp.src('./src/styles.scss')
    .pipe(plumber())
    .pipe(sass({errLogToConsole: true}))
    .pipe(prefix())
    .pipe(rename('styles.min.css'))
    .pipe(gulp.dest('./build/assets/'));

  done();
});


gulp.task('scripts', (done) => {
  gulp.src('./src/scripts.js')
    .pipe(plumber())
    .pipe(uglify())
    .pipe(rename('scripts.min.js'))
    .pipe(gulp.dest('./build/assets/'))

  done();
})


gulp.task('vendor', (done) => {
  gulp.src('./node_modules/pinch-zoom-js/dist/pinch-zoom.umd.min.js')
    .pipe(gulp.dest('./build/assets/'));

  done();
});


gulp.task('watch', (done) => {
  gulp.watch('./src/**/*', gulp.series('styles', 'scripts'));

  done();
});


gulp.task('default', gulp.parallel('styles', 'scripts', 'vendor', 'watch'));
