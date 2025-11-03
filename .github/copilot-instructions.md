# GitHub Copilot Instructions for baserCMS4 Plugin

このディレクトリは、**baserCMS4系（CakePHP2ベース）用プラグイン開発**のための
AI開発支援ガイドラインをまとめています。  
GitHub Copilot や Cursor などのAI支援ツールが開発を行う際は、以下のルールに従ってください。

---

## 基本方針

- baserCMS4 の既存仕様（PHP7.4 + CakePHP2系構文）に厳密に準拠すること。
- baserCMS4.7 以降 / PHP7.4 環境を想定。
- コアを直接改変せず、**プラグインとして機能を拡張**する方針を取る。
- `app/Plugin/` 以下に配置し、baserCMS管理画面から有効化できる構成を守る。
- セキュリティ・保守性・後方互換性を優先する。

---

## コーディングルール

- **クラス命名**: `CamelCase`。モデル名・コントローラ名には `PluginName` プレフィックスを付与。
- **メソッド命名**: CakePHP2標準のコールバック名・アクション名を尊重。
- **ファイル構成**: MVC構造に従い、以下を基本とする。
  ```
  PluginName/
  ├── Config/
  ├── Controller/
  ├── Model/
  ├── View/
  ├── webroot/
  └── Test/
  ```
- **テンプレート**: Viewディレクトリに `.php` ファイルを利用。HTML出力時は `h()` によるエスケープを徹底。
- **ロジック**: Fat Controllerを避け、Model または Component に切り出す。
- **コメント**: DocBlockを用い、関数の引数・戻り値を明記。

---

## テストと互換性

- ユニットテストは `CakeTestCase` を利用。
- baserCMSコア関数（例：`BcUtil`, `BcHtml`, `BcForm`）を直接モックしない。
- プラグインのインストール・アンインストール時にDB変更が必要な場合は、
  `Config/Schema/` ディレクトリにスキーマ定義を用意する。

---

## 開発時の注意点

- baserCMSコアファイル (`/lib/Baser/` 以下) を直接変更しない。
- コアイベント (`BcEventDispatcher`) にフックして拡張する。
- 他プラグインとの競合を避けるため、グローバル変数の使用は最小限に。
- `$this->BcBaser` や `$this->BcForm` など、コアヘルパーを優先利用する。

---
※ このファイルはAI支援ツールが baserCMS4 プラグイン開発時に参照する技術ルールです。
