(() => {
    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    const navigation = document.querySelector('.site-nav');
    const navigationShell = document.querySelector('.site-nav-shell');

    if (navigation && navigationShell) {
        const hero = document.querySelector('.site-hero, .account-hero');

        if (hero && 'IntersectionObserver' in window) {
            const navigationObserver = new IntersectionObserver(
                ([entry]) => {
                    navigationShell.classList.toggle(
                        'is-scrolled',
                        entry.intersectionRatio < 0.15
                    );
                },
                { threshold: [0, 0.15] }
            );

            navigationObserver.observe(hero);
        }
    }

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const inputId = toggle.getAttribute('aria-controls');
            const input = inputId ? document.getElementById(inputId) : null;

            if (!input) {
                return;
            }

            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.textContent = isVisible ? 'Show' : 'Hide';
            toggle.setAttribute('aria-pressed', String(!isVisible));
        });
    });

    const normalizePasswordValue = (value) =>
        value.toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');

    const passwordCandidateVariants = (value) => {
        const lowercase = value.toLocaleLowerCase();
        const canonical = lowercase
            .replaceAll('@', 'a')
            .replaceAll('$', 's')
            .replaceAll('0', 'o');

        return Array.from(new Set([
            normalizePasswordValue(lowercase),
            normalizePasswordValue(canonical)
        ].filter(Boolean)));
    };

    const isPredictablePassword = (value, policy) => {
        const normalized = normalizePasswordValue(value);

        if (/^(.{1,4})\1{3,}$/u.test(normalized)) {
            return true;
        }

        const commonPasswords = new Set(
            (policy.commonPasswords || []).map(normalizePasswordValue)
        );
        const commonRoots = (policy.commonRoots || []).map(normalizePasswordValue);

        return passwordCandidateVariants(value).some((candidate) => {
            if (commonPasswords.has(candidate)) {
                return true;
            }

            return commonRoots.some((root) => {
                if (candidate === root) {
                    return true;
                }

                if (!candidate.startsWith(root)) {
                    return false;
                }

                return /^[0-9]{1,8}$/.test(candidate.slice(root.length));
            });
        });
    };

    const contextTermsFromValue = (value) => {
        const terms = [value];
        const parts = value.split(/[^\p{L}\p{N}]+/u);

        parts.forEach((part) => {
            if (Array.from(part).length >= 4) {
                terms.push(part);
            }
        });

        const emailLocalPart = value.includes('@') ? value.split('@', 1)[0] : '';
        if (Array.from(emailLocalPart).length >= 4) {
            terms.push(emailLocalPart);
        }

        return terms.map(normalizePasswordValue).filter(
            (term) => Array.from(term).length >= 4
        );
    };

    const usesPasswordContext = (value, contextValues) => {
        const contextTerms = Array.from(new Set(
            contextValues.flatMap(contextTermsFromValue)
        ));

        return passwordCandidateVariants(value).some((candidate) =>
            contextTerms.some((term) => candidate.includes(term))
        );
    };

    const evaluatePassword = (value, policy, contextValues) => {
        const length = Array.from(value).length;
        const withinMaximum = length <= policy.maxLength
            && !/[\u0000-\u001f\u007f]/u.test(value);
        const validLength = length >= policy.minLength && withinMaximum;
        const mixedCase = withinMaximum
            && /\p{Ll}/u.test(value)
            && /\p{Lu}/u.test(value);
        const numberAndSymbol = withinMaximum
            && /\p{N}/u.test(value)
            && /[^\p{L}\p{N}\s]/u.test(value);

        return {
            validLength,
            mixedCase,
            numberAndSymbol,
            safeChoice: validLength
                && mixedCase
                && numberAndSymbol
                && !isPredictablePassword(value, policy)
                && !usesPasswordContext(value, contextValues)
        };
    };

    document.querySelectorAll('[data-password-input]').forEach((input) => {
        const meterId = input.getAttribute('data-password-input');
        const meter = meterId ? document.getElementById(meterId) : null;

        if (!meter) {
            return;
        }

        const strengthTrack = meter.querySelector('[role="meter"]');
        const strengthCopy = meter.querySelector('[data-strength-copy]');
        const strengthCount = meter.querySelector('[data-strength-count]');
        const requirementItems = Array.from(
            meter.querySelectorAll('[data-password-check]')
        );
        let policy;

        try {
            policy = JSON.parse(meter.dataset.passwordPolicy || '{}');
        } catch {
            policy = {};
        }

        policy = {
            minLength: Number(policy.minLength) || 15,
            maxLength: Number(policy.maxLength) || 128,
            commonPasswords: Array.isArray(policy.commonPasswords)
                ? policy.commonPasswords
                : [],
            commonRoots: Array.isArray(policy.commonRoots) ? policy.commonRoots : [],
            contextValues: Array.isArray(policy.contextValues)
                ? policy.contextValues
                : ['databruh']
        };

        const contextInputs = [
            meter.dataset.passwordNameInput,
            meter.dataset.passwordEmailInput
        ]
            .filter(Boolean)
            .map((inputId) => document.getElementById(inputId))
            .filter(Boolean);

        const updatePasswordStrength = () => {
            const value = input.value;
            const contextValues = [
                ...policy.contextValues,
                ...contextInputs.map((contextInput) => contextInput.value)
            ];
            const checks = value
                ? evaluatePassword(value, policy, contextValues)
                : {
                    validLength: false,
                    mixedCase: false,
                    numberAndSymbol: false,
                    safeChoice: false
                };
            const score = Object.values(checks).filter(Boolean).length;
            const requirementsMet = score === 4;
            const messages = {
                validLength: `Use ${policy.minLength}–${policy.maxLength} printable characters`,
                mixedCase: 'Add uppercase and lowercase letters',
                numberAndSymbol: 'Add at least one number and one symbol',
                safeChoice: 'Choose a password unrelated to common or personal terms'
            };
            const firstUnmetRequirement = Object.keys(checks).find(
                (key) => !checks[key]
            );
            const message = value
                ? requirementsMet
                    ? 'All security requirements met'
                    : messages[firstUnmetRequirement]
                : 'Complete all four requirements';

            meter.dataset.score = String(score);
            input.setCustomValidity(value && !requirementsMet ? message : '');

            if (strengthTrack) {
                strengthTrack.setAttribute('aria-valuenow', String(score));
                strengthTrack.setAttribute('aria-valuetext', message);
            }

            if (strengthCopy) {
                strengthCopy.textContent = message;
            }

            if (strengthCount) {
                strengthCount.textContent = `${score}/4 requirements`;
            }

            requirementItems.forEach((item) => {
                const checkName = item.dataset.passwordCheck;
                const isMet = Boolean(checks[checkName]);
                item.classList.toggle('is-met', isMet);
                item.dataset.state = isMet ? 'met' : 'pending';
            });
        };

        input.addEventListener('input', updatePasswordStrength);
        contextInputs.forEach((contextInput) => {
            contextInput.addEventListener('input', updatePasswordStrength);
        });
        updatePasswordStrength();
    });

    document.querySelectorAll('.access-accordion').forEach((accordion) => {
        const panels = Array.from(
            accordion.querySelectorAll('.accordion-panel')
        );

        const activatePanel = (selectedPanel) => {
            panels.forEach((panel) => {
                const isSelected = panel === selectedPanel;
                panel.classList.toggle('is-active', isSelected);
                panel.setAttribute('aria-expanded', String(isSelected));
            });
        };

        panels.forEach((panel, panelIndex) => {
            panel.addEventListener('click', () => activatePanel(panel));
            panel.addEventListener('focus', () => activatePanel(panel));
            panel.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                const direction = ['ArrowRight', 'ArrowDown'].includes(event.key)
                    ? 1
                    : -1;
                const nextIndex =
                    (panelIndex + direction + panels.length) % panels.length;

                panels[nextIndex].focus();
            });
        });
    });

    const feedback = document.querySelector('[data-feedback]');
    if (feedback) {
        window.requestAnimationFrame(() => feedback.focus());
    }

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const slides = Array.from(
            carousel.querySelectorAll('[data-carousel-slide]')
        );
        const previous = carousel.querySelector('[data-carousel-prev]');
        const next = carousel.querySelector('[data-carousel-next]');
        const status = carousel.querySelector('[data-carousel-status]');
        let currentIndex = Math.max(
            0,
            slides.findIndex((slide) => slide.classList.contains('is-active'))
        );
        let carouselTimeline = null;
        let isAnimating = false;

        const updateStatus = () => {
            if (status) {
                status.textContent = `${String(currentIndex + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
            }
        };

        const setBusyState = (isBusy) => {
            isAnimating = isBusy;
            carousel.setAttribute('aria-busy', String(isBusy));

            [previous, next].forEach((control) => {
                if (control) {
                    control.disabled = isBusy;
                }
            });
        };

        const applySlideState = (index) => {
            currentIndex = index;

            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === currentIndex;
                slide.classList.toggle('is-active', isActive);
                slide.hidden = !isActive;
                slide.setAttribute('aria-hidden', String(!isActive));
            });

            updateStatus();
        };

        const showSlide = (index, direction = 1, immediate = false) => {
            if (!slides.length) {
                return;
            }

            const nextIndex = (index + slides.length) % slides.length;

            if (isAnimating || (!immediate && nextIndex === currentIndex)) {
                return;
            }

            if (immediate || reducedMotion || !window.gsap) {
                carouselTimeline?.kill();
                applySlideState(nextIndex);
                setBusyState(false);
                return;
            }

            const outgoing = slides[currentIndex];
            const incoming = slides[nextIndex];

            setBusyState(true);
            currentIndex = nextIndex;
            updateStatus();

            incoming.hidden = false;
            incoming.classList.add('is-active');
            incoming.setAttribute('aria-hidden', 'false');
            outgoing.classList.remove('is-active');
            outgoing.setAttribute('aria-hidden', 'true');

            window.gsap.set(incoming, {
                autoAlpha: 0,
                x: direction * 34,
                scale: 0.985
            });

            carouselTimeline = window.gsap.timeline({
                onComplete: () => {
                    outgoing.hidden = true;
                    window.gsap.set([outgoing, incoming], {
                        clearProps: 'opacity,visibility,transform'
                    });
                    setBusyState(false);
                }
            });

            carouselTimeline
                .to(
                    outgoing,
                    {
                        autoAlpha: 0,
                        x: direction * -24,
                        scale: 0.985,
                        duration: 0.3,
                        ease: 'power2.in'
                    },
                    0
                )
                .to(
                    incoming,
                    {
                        autoAlpha: 1,
                        x: 0,
                        scale: 1,
                        duration: 0.48,
                        ease: 'power3.out'
                    },
                    0.1
                );
        };

        previous?.addEventListener('click', () =>
            showSlide(currentIndex - 1, -1)
        );
        next?.addEventListener('click', () =>
            showSlide(currentIndex + 1, 1)
        );
        showSlide(currentIndex, 0, true);
    });

    document.querySelectorAll('[data-confirm-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm-form');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const detailModalFocusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');
    let activeDetailModal = null;
    let activeDetailTrigger = null;
    let detailModalIsClosing = false;

    const setDetailTriggerState = (modalId, isOpen) => {
        document
            .querySelectorAll(`[data-detail-modal-open="${modalId}"]`)
            .forEach((trigger) => {
                trigger.setAttribute('aria-expanded', String(isOpen));
            });
    };

    const getDetailModalFocusables = (modal) =>
        Array.from(modal.querySelectorAll(detailModalFocusableSelector)).filter(
            (element) =>
                !element.hasAttribute('disabled') &&
                element.getAttribute('aria-hidden') !== 'true' &&
                (element.offsetWidth > 0 ||
                    element.offsetHeight > 0 ||
                    element.getClientRects().length > 0)
        );

    const finishDetailModalClose = (modal, trigger, restoreFocus) => {
        const modalId = modal.id;

        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('detail-modal-open');
        setDetailTriggerState(modalId, false);

        if (activeDetailModal === modal) {
            activeDetailModal = null;
            activeDetailTrigger = null;
        }

        detailModalIsClosing = false;

        if (restoreFocus && trigger?.isConnected) {
            trigger.focus({ preventScroll: true });
        }
    };

    const closeDetailModal = ({
        restoreFocus = true,
        immediate = false
    } = {}) => {
        if (!activeDetailModal || detailModalIsClosing) {
            return;
        }

        detailModalIsClosing = true;

        const modal = activeDetailModal;
        const trigger = activeDetailTrigger;
        const dialog = modal.querySelector('.detail-modal__dialog');
        const backdrop = modal.querySelector('.detail-modal__backdrop');
        const cards = Array.from(
            modal.querySelectorAll('.detail-modal__card')
        );
        const finish = () =>
            finishDetailModalClose(modal, trigger, restoreFocus);

        modal.classList.remove('is-open');

        if (
            !immediate &&
            !reducedMotion &&
            window.gsap &&
            dialog &&
            backdrop
        ) {
            window.gsap.killTweensOf([dialog, backdrop, ...cards]);
            window.gsap
                .timeline({ onComplete: finish })
                .to(cards, {
                    y: 10,
                    opacity: 0,
                    duration: 0.14,
                    stagger: 0.018,
                    ease: 'power2.in'
                })
                .to(
                    dialog,
                    {
                        y: 20,
                        scale: 0.988,
                        opacity: 0,
                        duration: 0.18,
                        ease: 'power2.in'
                    },
                    0
                )
                .to(
                    backdrop,
                    {
                        opacity: 0,
                        duration: 0.18,
                        ease: 'none'
                    },
                    0
                );
            return;
        }

        finish();
    };

    const openDetailModal = (trigger) => {
        const modalId = trigger.getAttribute('data-detail-modal-open');
        const modal = modalId ? document.getElementById(modalId) : null;
        const dialog = modal?.querySelector('.detail-modal__dialog');

        if (!modal || !dialog || activeDetailModal === modal) {
            return;
        }

        if (activeDetailModal) {
            closeDetailModal({ restoreFocus: false, immediate: true });
        }

        activeDetailModal = modal;
        activeDetailTrigger = trigger;
        detailModalIsClosing = false;
        modal.hidden = false;
        document.body.classList.add('detail-modal-open');
        setDetailTriggerState(modalId, true);

        window.requestAnimationFrame(() => {
            const backdrop = modal.querySelector('.detail-modal__backdrop');
            const cards = Array.from(
                modal.querySelectorAll('.detail-modal__card')
            );

            modal.classList.add('is-open');
            dialog.focus({ preventScroll: true });

            if (!reducedMotion && window.gsap && backdrop) {
                window.gsap.killTweensOf([dialog, backdrop, ...cards]);
                window.gsap.fromTo(
                    backdrop,
                    { opacity: 0 },
                    {
                        opacity: 1,
                        duration: 0.24,
                        ease: 'none'
                    }
                );
                window.gsap.fromTo(
                    dialog,
                    {
                        y: 28,
                        scale: 0.982,
                        opacity: 0
                    },
                    {
                        y: 0,
                        scale: 1,
                        opacity: 1,
                        duration: 0.42,
                        ease: 'power3.out'
                    }
                );
                window.gsap.fromTo(
                    cards,
                    {
                        y: 14,
                        opacity: 0
                    },
                    {
                        y: 0,
                        opacity: 1,
                        duration: 0.34,
                        stagger: 0.055,
                        delay: 0.08,
                        ease: 'power3.out'
                    }
                );
            }
        });
    };

    document.querySelectorAll('[data-detail-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => openDetailModal(trigger));
    });

    document.querySelectorAll('[data-detail-modal]').forEach((modal) => {
        modal
            .querySelectorAll('[data-detail-modal-close]')
            .forEach((control) => {
                control.addEventListener('click', () => closeDetailModal());
            });
    });

    document.addEventListener('keydown', (event) => {
        if (!activeDetailModal || detailModalIsClosing) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDetailModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements =
            getDetailModalFocusables(activeDetailModal);

        if (!focusableElements.length) {
            event.preventDefault();
            activeDetailModal
                .querySelector('.detail-modal__dialog')
                ?.focus({ preventScroll: true });
            return;
        }

        const firstFocusable = focusableElements[0];
        const lastFocusable =
            focusableElements[focusableElements.length - 1];

        if (
            event.shiftKey &&
            (document.activeElement === firstFocusable ||
                document.activeElement ===
                    activeDetailModal.querySelector('.detail-modal__dialog'))
        ) {
            event.preventDefault();
            lastFocusable.focus();
        } else if (
            !event.shiftKey &&
            document.activeElement === lastFocusable
        ) {
            event.preventDefault();
            firstFocusable.focus();
        }
    });

    const chartCards = Array.from(
        document.querySelectorAll('[data-chart-card]')
    );

    const activateChartCard = (card) => {
        if (card.dataset.chartActivated === 'true') {
            return;
        }

        const canvas = card.querySelector('canvas');

        card.dataset.chartActivated = 'true';
        card.classList.add('is-chart-active');

        if (canvas?.id) {
            window.DatabruhCharts?.play(canvas.id);
        }
    };

    if (reducedMotion || !window.gsap || !window.ScrollTrigger) {
        if (chartCards.length) {
            if (reducedMotion || !('IntersectionObserver' in window)) {
                chartCards.forEach(activateChartCard);
            } else {
                const chartObserver = new IntersectionObserver(
                    (entries, observer) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) {
                                return;
                            }

                            activateChartCard(entry.target);
                            observer.unobserve(entry.target);
                        });
                    },
                    {
                        rootMargin: '0px 0px -12% 0px',
                        threshold: 0.2
                    }
                );

                chartCards.forEach((card) => chartObserver.observe(card));
            }
        }

        document.documentElement.classList.add('motion-ready');
        return;
    }

    gsap.registerPlugin(ScrollTrigger);

    gsap.utils.toArray('[data-carousel]').forEach((carousel) => {
        gsap.fromTo(
            carousel,
            {
                scale: 0.955,
                opacity: 0.3
            },
            {
                scale: 1,
                opacity: 1,
                duration: 0.9,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: carousel,
                    start: 'top 88%',
                    once: true
                }
            }
        );
    });

    const heroTimeline = gsap.timeline({
        defaults: { duration: 0.68, ease: 'power3.out' }
    });

    if (navigation) {
        heroTimeline.from(navigation, {
            y: -12,
            opacity: 0,
            duration: 0.58
        });
    }

    const heroItems = gsap.utils.toArray('[data-hero-item]');
    if (heroItems.length) {
        heroTimeline.from(
            heroItems,
            {
                y: 18,
                opacity: 0,
                stagger: 0.08
            },
            navigation ? '-=0.28' : 0
        );
    }

    gsap.utils
        .toArray('[data-reveal]:not([data-stack-card]):not([data-chart-card])')
        .forEach((element) => {
        gsap.from(element, {
            y: 12,
            opacity: 0,
            duration: 0.62,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: element,
                start: 'top 88%',
                once: true
            }
        });
        });

    document.querySelectorAll('[data-scrub-text]').forEach((element) => {
        const sourceText = element.textContent.trim().replace(/\s+/g, ' ');

        if (!sourceText) {
            return;
        }

        element.setAttribute('aria-label', sourceText);
        element.textContent = '';

        const words = sourceText.split(' ').map((word, index, allWords) => {
            const wordElement = document.createElement('span');
            wordElement.className = 'scrub-word';
            wordElement.setAttribute('aria-hidden', 'true');
            wordElement.textContent =
                index === allWords.length - 1 ? word : `${word} `;
            element.appendChild(wordElement);
            return wordElement;
        });

        gsap.fromTo(
            words,
            { opacity: 0.14 },
            {
                opacity: 1,
                stagger: 0.045,
                ease: 'none',
                scrollTrigger: {
                    trigger: element,
                    start: 'top 88%',
                    end: 'bottom 52%',
                    scrub: 0.65
                }
            }
        );
        });

    const chartSection = document.querySelector('[data-chart-section]');
    const chartHeading = chartSection?.querySelector('[data-chart-heading]');

    chartCards.forEach((card) => {
        const chartWrap = card.querySelector('.chart-wrap');

        gsap.fromTo(
            card,
            {
                y: 36,
                opacity: 0.42
            },
            {
                y: 0,
                opacity: 1,
                duration: 0.72,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: card,
                    start: 'top 84%',
                    once: true,
                    onEnter: () => activateChartCard(card),
                    invalidateOnRefresh: true
                }
            }
        );

        if (chartWrap) {
            gsap.fromTo(
                chartWrap,
                {
                    clipPath: 'inset(8% 4% 8% 4%)',
                    scale: 0.985
                },
                {
                    clipPath: 'inset(0% 0% 0% 0%)',
                    scale: 1,
                    duration: 0.82,
                    ease: 'power2.out',
                    scrollTrigger: {
                        trigger: card,
                        start: 'top 86%',
                        once: true,
                        invalidateOnRefresh: true
                    }
                }
            );
        }
    });

    if (chartSection && chartHeading) {
        gsap.to(chartHeading, {
            '--chart-progress': 1,
            ease: 'none',
            scrollTrigger: {
                trigger: chartSection,
                start: 'top 78%',
                end: 'bottom 24%',
                scrub: 0.55,
                invalidateOnRefresh: true
            }
        });
    }

    gsap.utils
        .toArray('[data-stack-card]:not([data-chart-card])')
        .forEach((card, index) => {
        card.style.setProperty('--stack-index', String(index + 1));

        gsap.fromTo(
            card,
            {
                y: 72,
                scale: 0.965,
                opacity: 0.42
            },
            {
                y: 0,
                scale: 1,
                opacity: 1,
                ease: 'none',
                scrollTrigger: {
                    trigger: card,
                    start: 'top 96%',
                    end: 'top 68%',
                    scrub: 0.7
                }
            }
        );
        });

    document.documentElement.classList.add('motion-ready');
})();
