Plugins.mstra = {
  orig_content: "data-mstra-orig-content",
  self: this,
  convert: function (id) {
    const content = App.find(
      App.isCombinedMode()
        ? `.cdm[data-article-id="${id}"] .content-inner`
        : `.post[data-article-id="${id}"] .content`
    );

    const title = App.find(
      App.isCombinedMode()
        ? `.cdm[data-article-id="${id}"] .title`
        : `.post[data-article-id="${id}"] .title > a`
    );

    if (content.hasAttribute(self.orig_content)) {
      if (title.hasAttribute("title")) {
        title.text = title.getAttribute("title");
      }
      if (App.isCombinedMode()) Article.cdmMoveToId(id);
      return;
    }

    Notify.progress("Loading, please wait...");

    xhr.json(
      "backend.php",
      App.getPhArgs("mstra", "convert", { id: id }),
      (reply) => {
        if (content && reply.content) {
          if (reply.title) {
            title.text = reply.title;
          }
          Notify.close();
          if (App.isCombinedMode()) Article.cdmMoveToId(id);
        } else {
          Notify.error("Unable to convert via mstra for this article");
        }
      }
    );
  },
};

