# ContentsRepair プラグイン

ContentsRepair プラグインは、sqlite利用時に、コンテンツ管理のツリー構造で並べ替えがうまくいかなくなった場合に、ツリー構造の修復を試みるためのbaserCMS4系専用のプラグインです。


## 使い方

2段階の手順です。


### 1段階目

- DBのバックアップを取ってください。
- 管理側コンテンツ一覧画面で「全てを展開」し、スクリーンショットを撮ってください。


### 2段階目

1. 圧縮ファイルを解凍後、BASERCMS/app/Plugin/ContentsRepair に配置します。
2. 管理システムのプラグイン管理にアクセスし、表示されている ContentsRepair プラグイン をインストール（有効化）してください。
3. /admin/contents_repair/contents_repairs/ にアクセスしてください。
    - ※システム管理グループのユーザのみがアクセスできます。
    - 説明を記載しているため確認の上、実行してください。


## 使い方.2

- 本プラグインを有効化することで、コンテンツを削除した際に、ゴミ箱に入る動作が無効化されます。
    - 具体的には、ゴミ箱に入れる一次削除（論理削除）が、物理削除になります。
- baserの基本動作として、削除操作で「ゴミ箱に入ります」案内が出ますがそのまま削除となります。

### ゴミ箱に入る動作に戻したいとき

- 以下のファイルを Event ディレクトリ毎削除してください。
    - app/Plugin/ContentsRepair/Event/ContentsRepairModelEventListener.php


## Thanks

- [http://basercms.net](http://basercms.net/)
- [http://wiki.basercms.net/](http://wiki.basercms.net/)
- [http://doc.basercms.net/](http://doc.basercms.net/)
- [http://cakephp.jp](http://cakephp.jp)
- [Semantic Versioning 2.0.0](http://semver.org/lang/ja/)
