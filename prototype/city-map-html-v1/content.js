const sceneContent = {
  tonight: {label: "TONIGHT", detail: "Current show · venue · time"},
  weekend: {kicker: "HAPPENING THIS WEEKEND", title: "WHAT'S PLAYING<br>THIS WEEKEND", meta: "Show count · venue count · date range", action: "OPEN THE WEEKEND →"},
  upcoming: {label: "UPCOMING", detail: "Newly announced performances"},
  freeMusic: {label: "FREE<br>MUSIC", detail: "No-cover performances"},
  scene: {label: "THE<br>SCENE", detail: "Stories from Chattanooga music"}
};

for (const element of document.querySelectorAll("[data-bind]")) {
  const value = element.dataset.bind.split(".").reduce((item, key) => item?.[key], sceneContent);
  if (typeof value === "string") element.innerHTML = value;
}
