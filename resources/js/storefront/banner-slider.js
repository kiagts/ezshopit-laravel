/*
|--------------------------------------------------------------------------
| Storefront Banner Slider
|--------------------------------------------------------------------------
|
| Shared slider logic for:
| - Hero banner slider
| - Full-width banner slider
| - Promotional banner slider
|
| Required data attributes:
|
| data-banner-slider
| data-banner-slide
| data-banner-prev
| data-banner-next
| data-banner-dot
| data-banner-autoplay
|
|--------------------------------------------------------------------------
*/

const initializeBannerSliders = () => {

    const sliders = document.querySelectorAll(
        '[data-banner-slider]'
    );

    sliders.forEach((slider) => {

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Initialization
        |--------------------------------------------------------------------------
        */

        if (slider.dataset.bannerInitialized === 'true') {
            return;
        }

        slider.dataset.bannerInitialized = 'true';


        /*
        |--------------------------------------------------------------------------
        | Slider Elements
        |--------------------------------------------------------------------------
        */

        const slides = Array.from(
            slider.querySelectorAll(
                '[data-banner-slide]'
            )
        );

        const previousButton = slider.querySelector(
            '[data-banner-prev]'
        );

        const nextButton = slider.querySelector(
            '[data-banner-next]'
        );

        const dots = Array.from(
            slider.querySelectorAll(
                '[data-banner-dot]'
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Exit If There Are No Slides
        |--------------------------------------------------------------------------
        */

        if (slides.length === 0) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Slider State
        |--------------------------------------------------------------------------
        */

        let currentIndex = 0;

        let autoplayTimer = null;


        /*
        |--------------------------------------------------------------------------
        | Autoplay Delay
        |--------------------------------------------------------------------------
        */

        const autoplayDelay = parseInt(
            slider.dataset.bannerAutoplay || '6000',
            10
        );


        /*
        |--------------------------------------------------------------------------
        | Show Slide
        |--------------------------------------------------------------------------
        */

        const showSlide = (index) => {

            /*
            |--------------------------------------------------------------------------
            | Loop Around
            |--------------------------------------------------------------------------
            */

            if (index >= slides.length) {
                index = 0;
            }

            if (index < 0) {
                index = slides.length - 1;
            }


            /*
            |--------------------------------------------------------------------------
            | Update Slides
            |--------------------------------------------------------------------------
            */

            slides.forEach((slide, slideIndex) => {

                if (slideIndex === index) {

                    slide.classList.remove(
                        'opacity-0',
                        'pointer-events-none'
                    );

                    slide.classList.add(
                        'opacity-100'
                    );

                    slide.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                } else {

                    slide.classList.remove(
                        'opacity-100'
                    );

                    slide.classList.add(
                        'opacity-0',
                        'pointer-events-none'
                    );

                    slide.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                }

            });


            /*
            |--------------------------------------------------------------------------
            | Update Dots
            |--------------------------------------------------------------------------
            */

            dots.forEach((dot, dotIndex) => {

                const activeWidth =
                    slider.dataset.bannerDotActiveWidth || 'w-7';

                const inactiveWidth =
                    slider.dataset.bannerDotInactiveWidth || 'w-2.5';

                const activeColor =
                    slider.dataset.bannerDotActiveColor || 'bg-white';

                const inactiveColor =
                    slider.dataset.bannerDotInactiveColor || 'bg-white/60';


                dot.classList.remove(
                    'w-2',
                    'w-2.5',
                    'w-3',
                    'w-5',
                    'w-7',
                    'w-8',
                    'bg-white',
                    'bg-white/50',
                    'bg-white/60'
                );


                if (dotIndex === index) {

                    dot.classList.add(
                        activeWidth,
                        activeColor
                    );

                    dot.setAttribute(
                        'aria-current',
                        'true'
                    );

                } else {

                    dot.classList.add(
                        inactiveWidth,
                        inactiveColor
                    );

                    dot.removeAttribute(
                        'aria-current'
                    );

                }

            });


            /*
            |--------------------------------------------------------------------------
            | Save Current Index
            |--------------------------------------------------------------------------
            */

            currentIndex = index;

        };


        /*
        |--------------------------------------------------------------------------
        | Next Slide
        |--------------------------------------------------------------------------
        */

        const nextSlide = () => {

            showSlide(
                currentIndex + 1
            );

        };


        /*
        |--------------------------------------------------------------------------
        | Previous Slide
        |--------------------------------------------------------------------------
        */

        const previousSlide = () => {

            showSlide(
                currentIndex - 1
            );

        };


        /*
        |--------------------------------------------------------------------------
        | Stop Autoplay
        |--------------------------------------------------------------------------
        */

        const stopAutoplay = () => {

            if (!autoplayTimer) {
                return;
            }

            clearInterval(
                autoplayTimer
            );

            autoplayTimer = null;

        };


        /*
        |--------------------------------------------------------------------------
        | Start Autoplay
        |--------------------------------------------------------------------------
        */

        const startAutoplay = () => {

            stopAutoplay();

            if (slides.length <= 1) {
                return;
            }

            if (!autoplayDelay || autoplayDelay <= 0) {
                return;
            }

            autoplayTimer = setInterval(
                nextSlide,
                autoplayDelay
            );

        };


        /*
        |--------------------------------------------------------------------------
        | Previous Button
        |--------------------------------------------------------------------------
        */

        previousButton?.addEventListener(
            'click',
            (event) => {

                event.preventDefault();

                event.stopPropagation();

                previousSlide();

                startAutoplay();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Next Button
        |--------------------------------------------------------------------------
        */

        nextButton?.addEventListener(
            'click',
            (event) => {

                event.preventDefault();

                event.stopPropagation();

                nextSlide();

                startAutoplay();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Dot Navigation
        |--------------------------------------------------------------------------
        */

        dots.forEach((dot) => {

            dot.addEventListener(
                'click',
                (event) => {

                    event.preventDefault();

                    event.stopPropagation();


                    const index = parseInt(
                        dot.dataset.bannerDot,
                        10
                    );


                    if (Number.isNaN(index)) {
                        return;
                    }


                    showSlide(index);

                    startAutoplay();

                }
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Pause On Hover
        |--------------------------------------------------------------------------
        */

        slider.addEventListener(
            'mouseenter',
            stopAutoplay
        );

        slider.addEventListener(
            'mouseleave',
            startAutoplay
        );


        /*
        |--------------------------------------------------------------------------
        | Pause When User Focuses Inside Slider
        |--------------------------------------------------------------------------
        */

        slider.addEventListener(
            'focusin',
            stopAutoplay
        );

        slider.addEventListener(
            'focusout',
            startAutoplay
        );


        /*
        |--------------------------------------------------------------------------
        | Keyboard Navigation
        |--------------------------------------------------------------------------
        */

        slider.addEventListener(
            'keydown',
            (event) => {

                if (event.key === 'ArrowLeft') {

                    event.preventDefault();

                    previousSlide();

                    startAutoplay();

                }

                if (event.key === 'ArrowRight') {

                    event.preventDefault();

                    nextSlide();

                    startAutoplay();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Touch / Swipe Support
        |--------------------------------------------------------------------------
        */

        let touchStartX = 0;

        let touchEndX = 0;


        slider.addEventListener(
            'touchstart',
            (event) => {

                touchStartX =
                    event.changedTouches[0].screenX;

                stopAutoplay();

            },
            {
                passive: true,
            }
        );


        slider.addEventListener(
            'touchend',
            (event) => {

                touchEndX =
                    event.changedTouches[0].screenX;


                const difference =
                    touchStartX - touchEndX;


                const minimumSwipeDistance = 50;


                if (
                    Math.abs(difference) >=
                    minimumSwipeDistance
                ) {

                    if (difference > 0) {

                        nextSlide();

                    } else {

                        previousSlide();

                    }

                }


                startAutoplay();

            },
            {
                passive: true,
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Initialize Slider
        |--------------------------------------------------------------------------
        */

        showSlide(0);

        startAutoplay();

    });

};


/*
|--------------------------------------------------------------------------
| Initialize On DOM Ready
|--------------------------------------------------------------------------
*/

if (document.readyState === 'loading') {

    document.addEventListener(
        'DOMContentLoaded',
        initializeBannerSliders
    );

} else {

    initializeBannerSliders();

}


/*
|--------------------------------------------------------------------------
| Pause Sliders When Browser Tab Is Hidden
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'visibilitychange',
    () => {

        if (!document.hidden) {
            initializeBannerSliders();
        }

    }
);