(() => {
const floating = document.createElement("div");
document.body.appendChild(floating);

floating.style.position = "absolute";
floating.style.pointerEvents = "none";
floating.style.transition = "opacity 0.6s ease, transform 0.4s ease";
floating.style.transform = "translate(-21px, -50%)";
floating.style.opacity = "0";

const essentialProperties = [
    "color",
    "background-color",
    "font-family",
    "font-size",
    "font-weight",
    "font-style",
    "text-decoration",
    "text-transform",
    "letter-spacing",
    "line-height",
    "white-space",
    "padding",
    "border",
    "border-radius",
    "box-shadow",
    "width",
    "height",
    "margin",
    "max-width",
    "max-height",
    "min-width",
    "min-height",
    "display",
    "align-items",
    "justify-content",
    "top",
    "left",
    "right",
    "bottom",
    "z-index",
    "pointer-events",
    "overflow"
];

function updateFloatingFromAfter(balloonContainer, floating, requiredOpened = false) {
    if (requiredOpened && !balloonContainer.opened) {
        return;
    }

    balloonContainer.opened = true;

    const afterStyles = window.getComputedStyle(balloonContainer, "::after");
    const content = afterStyles.getPropertyValue("content").replace(/^"(.*)"$/, "$1").replace(/^"(.*)"$/, "$1");

    if (!content || content === "none") {
        floating.style.display = "none";
        return;
    }

    floating.textContent = content;

	copyEssentialAfterStyles(balloonContainer, floating);


    // Ensure position:absolute relative to the viewport
    floating.style.position = "absolute";
    floating.style.display = "block";
    floating.style.pointerEvents = "none"; // Prevent interference if it"s decorative

    // Calculate exact position using the ::after offsets
    const balloonContainerRect = balloonContainer.getBoundingClientRect();
    const parentStyles = window.getComputedStyle(balloonContainer);

    // Read offset values directly from the ::after
    const top = parseFloat(afterStyles.getPropertyValue("top")) || 0;
    const left = parseFloat(afterStyles.getPropertyValue("left")) || 0;
    const right = parseFloat(afterStyles.getPropertyValue("right")) || null;
    const bottom = parseFloat(afterStyles.getPropertyValue("bottom")) || null;

    let finalLeft = balloonContainerRect.left + left + window.scrollX;
    let finalTop = balloonContainerRect.top + top + window.scrollY;

    if (right !== null && afterStyles.getPropertyValue("right") !== "auto") {
        finalLeft = balloonContainerRect.right - right - floating.offsetWidth + window.scrollX;
    }

    if (bottom !== null && afterStyles.getPropertyValue("bottom") !== "auto") {
        finalTop = balloonContainerRect.bottom - bottom - floating.offsetHeight + window.scrollY;
    }
floating.style.pointerEvents = "none";
    floating.style.left = `${finalLeft}px`;
    floating.style.top = `${finalTop}px`;

	floating.style.opacity = "100";
floating.style.transform = "translate(-10px, -50%)";
}

function copyEssentialAfterStyles(source, balloonContainer) {
    const afterStyles = window.getComputedStyle(source, "::after");

    essentialProperties.forEach((prop) => {
        const value = afterStyles.getPropertyValue(prop);
        if (value) {
            balloonContainer.style.setProperty(prop, value);
        }
    });
}

function scheduleUpdate(requiredOpened = false) {
    requestAnimationFrame(() => {
        updateFloatingFromAfter(balloonContainer, floating, requiredOpened);
    });
}

balloonContainer.addEventListener("mouseenter", () => scheduleUpdate(false));
balloonContainer.addEventListener("mouseleave", () => {
	floating.style.opacity = "0";
	floating.style.transform = "translate(-21px, -50%)";
    balloonContainer.opened = false;
});
window.addEventListener("scroll", () => scheduleUpdate(true));
window.addEventListener("resize", () => scheduleUpdate(true));
})();